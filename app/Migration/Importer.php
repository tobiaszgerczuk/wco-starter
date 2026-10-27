<?php

namespace WCO\Starter\Migration;

/**
 * Imports an SQL dump and optionally runs a search and replace over the imported tables.
 *
 * Only a whitelist of statements is executed (DROP/CREATE TABLE, INSERT/REPLACE and a few harmless
 * SETs), and only on tables with this site's prefix. Anything else in the file is skipped and
 * reported, so a dump cannot create users, change grants or touch other databases.
 *
 * A dry run loads the file into temporary tables (prefix wcotmp_), reports what the replacement
 * would change and drops them again; the real tables are never touched.
 */
class Importer
{
    private const TMP = 'wcotmp_';
    private const IDENT = '(`[^`]+`|[A-Za-z0-9_$]+)';

    public function __construct(private \wpdb $db)
    {
    }

    /**
     * @param array{dry_run?: bool, search?: string, replace?: string, case_insensitive?: bool, guids?: bool, keep_urls?: bool} $o
     * @return array{statements: int, tables: array<string, array{inserted: int, changes: int, rows: int, seconds: float, note: string}>, skipped: string[], foreign: string[], source: array<string, string>, replaced: bool}
     */
    public function run(string $path, array $o): array
    {
        $db = $this->db;
        $prefix = $db->prefix;
        $dry = !empty($o['dry_run']);
        $target = $dry ? self::TMP : $prefix;
        $pairs = SearchReplace::pairs((string) ($o['search'] ?? ''), (string) ($o['replace'] ?? ''));

        $keep = [];
        if (!$dry && !empty($o['keep_urls'])) {
            foreach (['siteurl', 'home'] as $name) {
                $keep[$name] = (string) get_option($name);
            }
        }

        $out = ['statements' => 0, 'tables' => [], 'skipped' => [], 'foreign' => [], 'source' => [], 'replaced' => (bool) $pairs];
        $imported = []; // Target table name => original name.

        $mode = (string) $db->get_var('SELECT @@SESSION.sql_mode');
        $quiet = $db->suppress_errors(true);
        $this->drop_temp_tables();

        try {
            $db->query('SET FOREIGN_KEY_CHECKS=0');

            foreach (SqlReader::statements($path) as $sql) {
                $c = $this->classify($sql);

                if ($c['type'] === 'ignore') {
                    continue;
                }
                if ($c['type'] === 'reject') {
                    if (count($out['skipped']) < 10) {
                        $out['skipped'][] = mb_strimwidth($sql, 0, 90, '…');
                    }
                    continue;
                }

                if ($c['type'] === 'set') {
                    $this->exec($c['sql']);
                    continue;
                }

                $name = $c['table'];
                if (!str_starts_with($name, $prefix)) {
                    $out['foreign'][$name] = $name;
                    continue;
                }

                $new = $target . substr($name, strlen($prefix));
                $sql = substr($sql, 0, $c['offset']) . Exporter::ident($new) . substr($sql, $c['offset'] + $c['length']);

                $this->exec($sql);
                $out['statements']++;

                if ($c['type'] === 'create') {
                    $imported[$new] = $name;
                    $out['tables'][$name] = ['inserted' => 0, 'changes' => 0, 'rows' => 0, 'seconds' => 0.0, 'note' => ''];
                } elseif ($c['type'] === 'insert') {
                    $out['tables'][$name] ??= ['inserted' => 0, 'changes' => 0, 'rows' => 0, 'seconds' => 0.0, 'note' => ''];
                    $out['tables'][$name]['inserted'] += max(0, (int) $db->rows_affected);
                    $imported[$new] ??= $name;
                    if (str_ends_with($name, 'options') && count($out['source']) < 2) {
                        $this->detect_source_urls($sql, $out['source']);
                    }
                }
            }

            if (!$imported) {
                throw new \RuntimeException('W pliku nie znaleziono żadnej tabeli z prefiksem „' . $prefix . '”.');
            }

            if ($pairs) {
                $report = (new SearchReplace($db))->run(
                    array_keys($imported),
                    $pairs,
                    $dry,
                    !empty($o['case_insensitive']),
                    !empty($o['guids'])
                );

                foreach ($report['tables'] as $table => $stats) {
                    $out['tables'][$imported[$table]] = array_merge($out['tables'][$imported[$table]], $stats);
                }
            }

            foreach ($keep as $option => $value) {
                $db->update($prefix . 'options', ['option_value' => $value], ['option_name' => $option]);
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            $db->query($db->prepare('SET SESSION sql_mode = %s', $mode));
            $db->set_charset($db->dbh, $db->charset, $db->collate);
            $this->drop_temp_tables($dry ? array_keys($imported) : []);
            $db->suppress_errors($quiet);

            if (!$dry) {
                wp_cache_flush();
                delete_option('rewrite_rules');
            }
        }

        return $out;
    }

    private function exec(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            throw new \RuntimeException('Błąd SQL: ' . $this->db->last_error . ' — w poleceniu: ' . mb_strimwidth($sql, 0, 120, '…'));
        }
    }

    /**
     * @return array{type: string, sql?: string, table?: string, offset?: int, length?: int}
     */
    private function classify(string $sql): array
    {
        // mysqldump wraps version-specific statements in /*!NNNNN ... */; unwrap and look inside.
        if (str_starts_with($sql, '/*') && strpos($sql, '*/') === strlen($sql) - 2) {
            $inner = trim((string) preg_replace('~^/\*!?(?:M!)?\d*\s*~', '', substr($sql, 0, -2)));

            return $this->classify_set($inner);
        }

        $sql = (string) preg_replace('~^(?:\s*/\*.*?\*/)+\s*~s', '', $sql);

        if (preg_match('/^SET\s/i', $sql)) {
            return $this->classify_set($sql);
        }

        $id = self::IDENT;
        $patterns = [
            'drop' => "/^DROP\\s+TABLE\\s+IF\\s+EXISTS\\s+$id\\s*\$/i",
            'create' => "/^CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?$id\\s*\\(/i",
            'insert' => "/^(?:INSERT|REPLACE)\\s+(?:IGNORE\\s+)?INTO\\s+$id\\s*(?:\\(|VALUES\\b)/i",
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE)) {
                return [
                    'type' => $type,
                    'table' => trim($m[1][0], '`'),
                    'offset' => $m[1][1],
                    'length' => strlen($m[1][0]),
                ];
            }
        }

        // Lock and transaction statements are not needed for a plain import.
        if (preg_match('/^(?:LOCK|UNLOCK)\s+TABLES?\b|^(?:START\s+TRANSACTION|BEGIN|COMMIT)\b/i', $sql)) {
            return ['type' => 'ignore'];
        }

        return ['type' => 'reject'];
    }

    /** @return array{type: string, sql?: string} */
    private function classify_set(string $sql): array
    {
        $allowed = '/^SET\s+NAMES\s+\w+(?:\s+COLLATE\s+\w+)?$'
            . '|^SET\s+(?:SESSION\s+)?(?:FOREIGN_KEY_CHECKS|UNIQUE_CHECKS)\s*=\s*[01]$'
            . '|^SET\s+(?:SESSION\s+)?SQL_MODE\s*=\s*([\'"])[A-Za-z_,]*\1$/i';

        // Statements that only save or restore state in @variables, ALTER ... KEYS, comments: nothing to do.
        return preg_match($allowed, $sql) ? ['type' => 'set', 'sql' => $sql] : ['type' => 'ignore'];
    }

    /** @param array<string, string> $found */
    private function detect_source_urls(string $insert, array &$found): void
    {
        foreach (['siteurl', 'home'] as $name) {
            if (!isset($found[$name]) && preg_match("/\\(\\d+,'$name','((?:https?:)?\\/\\/[^']+)'/", $insert, $m)) {
                $found[$name] = rtrim($m[1], '/');
            }
        }
    }

    /** @param string[] $tables Extra temporary tables to drop besides any leftovers. */
    private function drop_temp_tables(array $tables = []): void
    {
        $db = $this->db;
        $like = $db->esc_like(self::TMP) . '%';

        foreach ((array) $db->get_col($db->prepare('SHOW TABLES LIKE %s', $like)) as $table) {
            $tables[] = $table;
        }

        foreach (array_unique($tables) as $table) {
            if (str_starts_with($table, self::TMP)) {
                $db->query('DROP TABLE IF EXISTS ' . Exporter::ident($table));
            }
        }
    }
}
