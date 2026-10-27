<?php

namespace WCO\Starter\DbTools;

/**
 * Long-running database jobs executed in short steps (so they survive shared-hosting time limits).
 * The state lives in a JSON file in a protected uploads folder, NOT in the database, because an
 * import replaces the database it runs in.
 *
 * Import safety:
 *  - only table statements on tables with the dump's own prefix are executed (INSERT, REPLACE, UPDATE,
 *    DELETE, CREATE/DROP/ALTER/TRUNCATE TABLE and harmless SET); everything else is skipped and counted;
 *  - an optional full backup is written first;
 *  - the current admin user and the site address / active theme / plugins are kept (restored at the end
 *    of every step so the session survives the import).
 */
final class DbJob
{
    public const DIR = 'wco-db-tools';
    private const KEEP_OPTIONS = ['siteurl', 'home', 'template', 'stylesheet', 'active_plugins', 'wco_db_tools_enabled'];
    private const SAFE_SET = '/^SET\s+(NAMES|FOREIGN_KEY_CHECKS|UNIQUE_CHECKS|SQL_MODE|SESSION\s+sql_mode|@OLD_\w+|@saved_\w+|@@?(?:SESSION\.)?(?:sql_mode|foreign_key_checks|unique_checks)|character_set_\w+|time_zone|SQL_NOTES|sql_notes|autocommit)\b/i';

    // ------------------------------------------------------------------ storage

    public static function dir(): string
    {
        $base = wp_upload_dir(null, false)['basedir'] . '/' . self::DIR;
        if (!is_dir($base)) {
            wp_mkdir_p($base);
        }
        if (!file_exists($base . '/.htaccess')) {
            file_put_contents($base . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!file_exists($base . '/index.php')) {
            file_put_contents($base . '/index.php', "<?php\n// Silence is golden.\n");
        }

        return $base;
    }

    public static function valid_id(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}$/', $id);
    }

    public static function upload_path(string $token): string
    {
        return self::dir() . '/upload-' . $token . '.sql';
    }

    public static function load(string $id): ?array
    {
        if (!self::valid_id($id) || !is_file(self::dir() . "/job-$id.json")) {
            return null;
        }

        return json_decode((string) file_get_contents(self::dir() . "/job-$id.json"), true) ?: null;
    }

    private static function save(array $job): void
    {
        file_put_contents(self::dir() . '/job-' . $job['id'] . '.json', wp_json_encode($job));
    }

    /** @return array<int, array{name:string,size:int,time:int}> */
    public static function backups(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/backup-*.sql') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    private static function prune(): void
    {
        foreach (array_slice(self::backups(), 10) as $b) {
            @unlink(self::dir() . '/' . $b['name']);
        }
        foreach (array_merge(glob(self::dir() . '/job-*.json') ?: [], glob(self::dir() . '/upload-*.sql') ?: []) as $f) {
            if (filemtime($f) < time() - 2 * DAY_IN_SECONDS) {
                @unlink($f);
            }
        }
    }

    // ------------------------------------------------------------------ analysis (dry run for an import file)

    /**
     * Reads a dump without touching the database: statements, tables, rows and how many times each
     * search string occurs. Also reports statements an import would refuse to run.
     *
     * @param array<int, array{0:string,1:string}> $pairs
     */
    public static function analyze(string $path, array $pairs, bool $ci): array
    {
        @set_time_limit(120);
        $pairs = SearchReplace::expand_pairs($pairs);
        $reader = new SqlReader($path);
        $reader->open(0);

        $tables = [];
        $total = 0;
        $blocked = [];
        $header = ['scope' => '', 'site' => '', 'prefix' => ''];
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        foreach ($header as $k => $_) {
            if (preg_match('/^-- ' . $k . ': (.+)$/m', $head, $m)) {
                $header[$k] = trim($m[1]);
            }
        }

        while (($sql = $reader->next()) !== null) {
            $total++;
            [$kind, $table] = self::classify($sql, '');
            if ($kind === 'blocked') {
                if (count($blocked) < 10) {
                    $blocked[] = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 120);
                }
                continue;
            }
            if ($table === null) {
                continue;
            }
            $tables[$table] ??= ['statements' => 0, 'rows' => 0, 'matches' => 0];
            $tables[$table]['statements']++;
            if (stripos($sql, 'INSERT') === 0 || stripos($sql, 'REPLACE') === 0) {
                $tables[$table]['rows'] += substr_count($sql, "),\n(") + 1;
            }
            foreach ($pairs as [$search]) {
                $tables[$table]['matches'] += $ci ? substr_count(mb_strtolower($sql), mb_strtolower($search)) : substr_count($sql, $search);
            }
        }
        $reader->close();

        if ($header['prefix'] === '') {
            foreach (array_keys($tables) as $t) {
                if (preg_match('/^(.*)options$/', $t, $m)) {
                    $header['prefix'] = $m[1];
                    break;
                }
            }
        }
        ksort($tables);

        return ['statements' => $total, 'tables' => $tables, 'blocked' => $blocked, 'header' => $header];
    }

    // ------------------------------------------------------------------ statements

    /** @return array{0:string,1:?string} kind (run|skip|blocked) and the first table the statement touches */
    public static function classify(string $sql, string $prefix): array
    {
        $s = ltrim($sql);
        if (preg_match('~^/\*!\d*\s*(.*?)\s*\*/$~s', $s, $m)) {
            $s = $m[1];
        }
        if (!preg_match('/^(\w+)/', $s, $m)) {
            return ['skip', null];
        }
        $verb = strtoupper($m[1]);

        if (in_array($verb, ['START', 'BEGIN', 'COMMIT', 'ROLLBACK', 'LOCK', 'UNLOCK'], true)) {
            return ['skip', null];
        }
        if ($verb === 'SET') {
            return [preg_match(self::SAFE_SET, $s) ? 'run' : 'blocked', null];
        }

        $patterns = [
            'INSERT' => '/^(?:INSERT|REPLACE)(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE))*\s+(?:INTO\s+)?`([^`]+)`/i',
            'REPLACE' => '/^(?:INSERT|REPLACE)(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE))*\s+(?:INTO\s+)?`([^`]+)`/i',
            'UPDATE' => '/^UPDATE(?:\s+(?:LOW_PRIORITY|IGNORE))*\s+`([^`]+)`/i',
            'DELETE' => '/^DELETE(?:\s+(?:LOW_PRIORITY|QUICK|IGNORE))*\s+(?:\w+\s+)?FROM\s+`([^`]+)`/i',
            'DROP' => '/^DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`([^`]+)`\s*$/i',
            'CREATE' => '/^CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`([^`]+)`/i',
            'ALTER' => '/^ALTER\s+TABLE\s+`([^`]+)`/i',
            'TRUNCATE' => '/^TRUNCATE(?:\s+TABLE)?\s+`([^`]+)`/i',
        ];
        if (!isset($patterns[$verb]) || !preg_match($patterns[$verb], $s, $t)) {
            return ['blocked', null];
        }
        if ($prefix !== '' && !str_starts_with($t[1], $prefix)) {
            return ['blocked', $t[1]];
        }

        return ['run', $t[1]];
    }

    // ------------------------------------------------------------------ creating jobs

    /**
     * @param array $p mode (import|replace), pairs, case_insensitive, include_guid, dry, backup,
     *                 keep_user, keep_settings, token (import), tables (replace)
     */
    public static function create(array $p): array
    {
        global $wpdb;

        $mode = ($p['mode'] ?? '') === 'import' ? 'import' : 'replace';
        $has_pairs = (bool) array_filter($p['pairs'] ?? [], static fn ($x) => is_array($x) && (string) ($x[0] ?? '') !== '');
        if ($mode === 'replace' && !$has_pairs) {
            throw new \RuntimeException('Podaj, czego szukać.');
        }
        $id = bin2hex(random_bytes(12));
        $job = [
            'id' => $id,
            'mode' => $mode,
            'user' => get_current_user_id(),
            'created' => time(),
            'pairs' => array_values(array_filter($p['pairs'] ?? [], static fn ($x) => is_array($x) && (string) ($x[0] ?? '') !== '')),
            'ci' => !empty($p['case_insensitive']),
            'guid' => !empty($p['include_guid']),
            'dry' => !empty($p['dry']),
            'phase' => $mode === 'import' ? 'sql' : 'replace',
            'offset' => 0,
            'size' => 0,
            'stats' => ['run' => 0, 'skipped' => 0, 'blocked' => 0, 'failed' => 0],
            'errors' => [],
            'blocked_samples' => [],
            'tables' => [],
            'table_idx' => 0,
            'cursor' => 0,
            'report' => [],
            'backup' => null,
            'keep' => [],
        ];

        if ($mode === 'import') {
            $token = (string) ($p['token'] ?? '');
            $file = self::upload_path($token);
            if (!self::valid_id($token) || !is_file($file)) {
                throw new \RuntimeException('Nie znaleziono wczytanego pliku SQL. Wczytaj go ponownie.');
            }
            $head = (string) file_get_contents($file, false, null, 0, 4096);
            $src = '';
            if (preg_match('/^-- prefix: (\w+)$/m', $head, $m)) {
                $src = $m[1];
            } else {
                $reader = new SqlReader($file);
                $reader->open(0);
                for ($i = 0; $i < 400 && ($sql = $reader->next()) !== null; $i++) {
                    if (preg_match('/`(\w*?)options`/', $sql, $m)) {
                        $src = $m[1];
                        break;
                    }
                }
                $reader->close();
            }
            $job['file'] = $file;
            $job['size'] = (int) filesize($file);
            $job['src_prefix'] = $src !== '' ? $src : $wpdb->prefix;
            $job['dst_prefix'] = $wpdb->prefix;
            $job['dry'] = false;
            if (!empty($p['keep_settings'])) {
                foreach (self::KEEP_OPTIONS as $name) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
                    if ($row) {
                        $job['keep']['options'][$name] = $row;
                    }
                }
            }
            if (!empty($p['keep_user'])) {
                $uid = get_current_user_id();
                $job['keep']['user'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID = %d", $uid), ARRAY_A);
                $job['keep']['usermeta'] = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d", $uid), ARRAY_A);
            }
        } else {
            $valid = SearchReplace::tables();
            $job['tables'] = array_values(array_intersect($valid, (array) ($p['tables'] ?? $valid)));
            if (!$job['tables']) {
                throw new \RuntimeException('Wybierz co najmniej jedną tabelę.');
            }
        }

        if (!$job['dry'] && !empty($p['backup'])) {
            $job['backup'] = 'backup-' . ($mode === 'import' ? 'before-import' : 'before-replace') . '-' . gmdate('Ymd-His') . '.sql';
            SqlExporter::to_file(self::dir() . '/' . $job['backup'], 'full');
        }
        self::prune();
        self::save($job);

        return self::status($job, $mode === 'import' ? 'Plik wczytany, zaczynam import…' : 'Zaczynam…');
    }

    // ------------------------------------------------------------------ stepping

    public static function step(string $id, float $budget = 4.0): array
    {
        $job = self::load($id);
        if (!$job || (int) $job['user'] !== get_current_user_id()) {
            throw new \RuntimeException('Nie znaleziono zadania (mogło wygasnąć).');
        }
        @set_time_limit(60);
        $deadline = microtime(true) + $budget;
        $message = '';

        switch ($job['phase']) {
            case 'sql':
                $message = self::step_sql($job, $deadline);
                break;
            case 'fix':
                self::step_fix($job);
                $job['phase'] = $job['pairs'] ? 'replace' : 'finish';
                $message = 'Poprawiam prefiksy i ustawienia…';
                break;
            case 'replace':
                $message = self::step_replace($job, $deadline);
                break;
            case 'finish':
                self::step_finish($job);
                $job['phase'] = 'done';
                $message = 'Gotowe.';
                break;
        }

        self::save($job);

        return self::status($job, $message);
    }

    private static function step_sql(array &$job, float $deadline): string
    {
        global $wpdb;

        $wpdb->suppress_errors(true);
        $wpdb->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        $src = $job['src_prefix'];
        $dst = $job['dst_prefix'];
        $reader = new SqlReader($job['file']);
        $reader->open((int) $job['offset']);
        $eof = false;

        while (true) {
            $sql = $reader->next();
            if ($sql === null) {
                $eof = true;
                break;
            }
            [$kind, $table] = self::classify($sql, $src);
            if ($kind === 'skip') {
                $job['stats']['skipped']++;
            } elseif ($kind === 'blocked') {
                $job['stats']['blocked']++;
                if (count($job['blocked_samples']) < 10) {
                    $job['blocked_samples'][] = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 140);
                }
            } else {
                if ($src !== $dst && $table !== null) {
                    $sql = self::remap_prefix($sql, $src, $dst);
                }
                $wpdb->last_error = '';
                $wpdb->query($sql);
                if ($wpdb->last_error !== '') {
                    $job['stats']['failed']++;
                    if (count($job['errors']) < 25) {
                        $job['errors'][] = mb_substr($wpdb->last_error, 0, 200) . ' — ' . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 90);
                    }
                } else {
                    $job['stats']['run']++;
                }
            }
            if (microtime(true) >= $deadline) {
                break;
            }
        }
        $job['offset'] = $reader->offset();
        $reader->close();

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        self::restore_kept($job);
        $wpdb->suppress_errors(false);

        if ($eof) {
            $job['phase'] = 'fix';
        }

        return sprintf('Import: wykonano %d instrukcji…', $job['stats']['run']);
    }

    private static function remap_prefix(string $sql, string $src, string $dst): string
    {
        $pos = stripos($sql, ' VALUES');
        $cut = $pos !== false && $pos < 3000 ? $pos : min(strlen($sql), 4000);
        $head = preg_replace('/`' . preg_quote($src, '/') . '/', '`' . $dst, substr($sql, 0, $cut));

        return $head . substr($sql, $cut);
    }

    private static function step_fix(array &$job): void
    {
        global $wpdb;

        $wpdb->suppress_errors(true);
        $src = $job['src_prefix'];
        $dst = $job['dst_prefix'];
        if ($src !== $dst) {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s", $dst . 'user_roles', $src . 'user_roles'));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->usermeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d)) WHERE meta_key LIKE %s",
                $dst,
                strlen($src) + 1,
                $wpdb->esc_like($src) . '%'
            ));
        }
        self::restore_kept($job);
        $wpdb->suppress_errors(false);
    }

    /** Put back what must survive the import (idempotent; called at the end of every step). */
    private static function restore_kept(array $job): void
    {
        global $wpdb;

        foreach ($job['keep']['options'] ?? [] as $name => $row) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
                $name,
                $row['option_value'],
                $row['autoload']
            ));
        }
        if (!empty($job['keep']['user'])) {
            $user = $job['keep']['user'];
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->users} WHERE ID = %d OR user_login = %s", $user['ID'], $user['user_login']));
            $wpdb->insert($wpdb->users, $user);
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE user_id = %d", $user['ID']));
            foreach ($job['keep']['usermeta'] ?? [] as $m) {
                $wpdb->insert($wpdb->usermeta, ['user_id' => $user['ID'], 'meta_key' => $m['meta_key'], 'meta_value' => $m['meta_value']]);
            }
        }
    }

    private static function step_replace(array &$job, float $deadline): string
    {
        $pairs = SearchReplace::expand_pairs($job['pairs']);
        if (!$job['tables']) {
            $job['tables'] = SearchReplace::tables();
            $job['table_idx'] = 0;
            $job['cursor'] = 0;
        }
        $message = '';
        while ($job['table_idx'] < count($job['tables']) && microtime(true) < $deadline) {
            $table = $job['tables'][$job['table_idx']];
            $cursor = (int) $job['cursor'];
            $res = SearchReplace::process_table($table, $pairs, $job['dry'], $job['ci'], $job['guid'], $cursor, $deadline);
            $r = $job['report'][$table] ?? ['rows' => 0, 'cells' => 0, 'skipped' => 0, 'note' => ''];
            $r['rows'] += $res['rows'];
            $r['cells'] += $res['cells'];
            $r['skipped'] += $res['skipped'];
            $r['note'] = $res['note'] ?: $r['note'];
            $job['report'][$table] = $r;
            $job['cursor'] = $cursor;
            $message = $table;
            if ($res['done']) {
                $job['table_idx']++;
                $job['cursor'] = 0;
            }
        }
        if ($job['table_idx'] >= count($job['tables'])) {
            $job['phase'] = $job['mode'] === 'import' ? 'finish' : 'done';
            if ($job['phase'] === 'done') {
                self::step_finish($job);
            }
        }

        return ($job['dry'] ? 'Próba: ' : 'Zamiana: ') . $message;
    }

    private static function step_finish(array &$job): void
    {
        global $wpdb;

        if (!$job['dry']) {
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'");
            delete_option('rewrite_rules');
            wp_cache_flush();
        }
        if (!empty($job['file']) && is_file($job['file'])) {
            @unlink($job['file']);
        }
        $job['finished'] = time();
        unset($job['keep']); // do not leave the kept user's hash and session tokens on disk
    }

    /** @return array<string, mixed> */
    private static function status(array $job, string $message): array
    {
        $phase = $job['phase'];
        $tables = max(1, count($job['tables']));
        $progress = match ($phase) {
            'sql' => $job['size'] ? (int) min(70, round($job['offset'] / $job['size'] * 70)) : 0,
            'fix' => 71,
            'replace' => $job['mode'] === 'import'
                ? 72 + (int) round($job['table_idx'] / $tables * 26)
                : (int) round($job['table_idx'] / $tables * 100),
            'finish' => 99,
            default => 100,
        };

        return [
            'id' => $job['id'],
            'mode' => $job['mode'],
            'dry' => $job['dry'],
            'phase' => $phase,
            'done' => $phase === 'done',
            'progress' => $progress,
            'message' => $message,
            'stats' => $job['stats'],
            'errors' => $job['errors'],
            'blocked' => $job['blocked_samples'],
            'backup' => $job['backup'],
            'report' => $job['report'],
        ];
    }
}
