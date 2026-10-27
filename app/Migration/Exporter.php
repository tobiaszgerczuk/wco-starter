<?php

namespace WCO\Starter\Migration;

/**
 * Writes the site's tables as a mysqldump-style SQL file using $wpdb only, so it needs neither
 * shell access nor the mysqldump binary. Rows are fetched in batches and flushed in chunks, which
 * keeps memory flat for big tables.
 */
class Exporter
{
    private const BATCH = 500;
    private const CHUNK = 524288;

    public function __construct(private \wpdb $db)
    {
    }

    /** @return string[] Base tables (no views) that carry this site's table prefix. */
    public function tables(): array
    {
        $names = [];
        foreach ((array) $this->db->get_results("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", ARRAY_N) as $row) {
            if (str_starts_with((string) $row[0], $this->db->prefix)) {
                $names[] = (string) $row[0];
            }
        }

        return $names;
    }

    public static function filename(bool $gzip): string
    {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);

        return sanitize_file_name($host . '-' . gmdate('Y-m-d-His') . '.sql' . ($gzip ? '.gz' : ''));
    }

    /**
     * @param callable(string): mixed $write Receives the dump piece by piece.
     * @param string[] $tables
     */
    public function export(callable $write, array $tables, bool $skipTransients = true): void
    {
        $write("-- WCO Starter database export\n"
            . '-- Site: ' . home_url() . "\n"
            . "-- Table prefix: {$this->db->prefix}\n"
            . '-- Generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n"
            . '-- WordPress ' . get_bloginfo('version') . ', MySQL ' . $this->db->db_version() . "\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            $this->export_table($write, $table, $skipTransients);
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** Writes the export into a file (gzip when the path ends in .gz). */
    public function to_file(string $path, array $tables, bool $skipTransients = true): void
    {
        $gzip = str_ends_with($path, '.gz');
        $fh = $gzip ? gzopen($path, 'wb6') : fopen($path, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Nie można zapisać pliku kopii zapasowej.');
        }

        try {
            $this->export($gzip ? static fn (string $s) => gzwrite($fh, $s) : static fn (string $s) => fwrite($fh, $s), $tables, $skipTransients);
        } finally {
            $gzip ? gzclose($fh) : fclose($fh);
        }
    }

    /** Saves a gzip backup in a protected uploads folder and returns its path. */
    public function backup(string $label = 'backup'): string
    {
        $dir = trailingslashit(wp_upload_dir()['basedir']) . 'wco-db-backups';
        wp_mkdir_p($dir);
        if (!is_file("$dir/.htaccess")) {
            file_put_contents("$dir/.htaccess", "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            file_put_contents("$dir/index.php", "<?php\n// Silence is golden.\n");
        }

        $path = sprintf('%s/%s-%s-%s.sql.gz', $dir, $label, gmdate('Y-m-d-His'), wp_generate_password(8, false));
        $this->to_file($path, $this->tables(), true);

        return $path;
    }

    private function export_table(callable $write, string $table, bool $skipTransients): void
    {
        $db = $this->db;
        $quoted = self::ident($table);

        $create = $db->get_row("SHOW CREATE TABLE $quoted", ARRAY_N);
        if (!$create || empty($create[1])) {
            return;
        }

        $write("--\n-- Table $quoted\n--\n\nDROP TABLE IF EXISTS $quoted;\n{$create[1]};\n\n");

        $kinds = [];
        $names = [];
        $primary = [];
        foreach ((array) $db->get_results("SHOW COLUMNS FROM $quoted", ARRAY_A) as $col) {
            $type = strtolower((string) $col['Type']);
            $names[] = self::ident((string) $col['Field']);
            $kinds[] = preg_match('/blob|binary/', $type) ? 'bin' : (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|year)/', $type) ? 'num' : 'str');
            if ($col['Key'] === 'PRI') {
                $primary[] = self::ident((string) $col['Field']);
            }
        }

        $where = '';
        if ($skipTransients && $table === $db->prefix . 'options') {
            $where = " WHERE LEFT(option_name, 11) <> '_transient_' AND LEFT(option_name, 16) <> '_site_transient_'";
        }
        $order = $primary ? ' ORDER BY ' . implode(',', $primary) : '';
        $head = "INSERT INTO $quoted (" . implode(',', $names) . ") VALUES\n";

        $buffer = '';
        for ($offset = 0;; $offset += self::BATCH) {
            $rows = $db->get_results("SELECT * FROM $quoted$where$order LIMIT " . self::BATCH . " OFFSET $offset", ARRAY_N);
            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $i => $value) {
                    $values[] = $this->literal($value, $kinds[$i] ?? 'str');
                }
                $buffer .= ($buffer === '' ? $head : ",\n") . '(' . implode(',', $values) . ')';

                if (strlen($buffer) >= self::CHUNK) {
                    $write($buffer . ";\n");
                    $buffer = '';
                }
            }

            if (count($rows) < self::BATCH) {
                break;
            }
        }

        $db->flush();
        $write(($buffer !== '' ? $buffer . ";\n" : '') . "\n");
    }

    private function literal(?string $value, string $kind): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($kind === 'num' && is_numeric($value)) {
            return $value;
        }
        if ($kind === 'bin') {
            return $value === '' ? "''" : '0x' . bin2hex($value);
        }

        return "'" . $this->db->remove_placeholder_escape($this->db->_real_escape($value)) . "'";
    }

    public static function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
