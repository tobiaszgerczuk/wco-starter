<?php

namespace WCO\Starter\Migration;

/**
 * Database-wide search and replace in the spirit of the Better Search Replace plugin: serialized
 * PHP data is unserialized, replaced recursively and serialized again, so string lengths stay
 * valid. Only rows that actually change are written, and a dry run writes nothing.
 */
class SearchReplace
{
    private const BATCH = 200;

    private int $found = 0;

    public function __construct(private \wpdb $db)
    {
    }

    /**
     * Turns the two textareas into [search, replace] pairs: one pair per line, matched by line
     * number (a single replacement line applies to every search line). Slashed URLs also get their
     * JSON-escaped variant (https:\/\/example.com) so page-builder data is covered.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function pairs(string $search, string $replace): array
    {
        $searches = array_values(array_filter(preg_split('/\R/', $search) ?: [], static fn (string $s): bool => $s !== ''));
        $replaces = preg_split('/\R/', $replace) ?: [''];

        $pairs = [];
        foreach ($searches as $i => $s) {
            $r = count($replaces) === 1 ? $replaces[0] : ($replaces[$i] ?? '');
            if ($s === $r) {
                continue;
            }
            $pairs[] = [$s, $r];

            if (str_contains($s, '/')) {
                $js = str_replace('/', '\/', $s);
                $jr = str_replace('/', '\/', $r);
                if ($js !== $jr) {
                    $pairs[] = [$js, $jr];
                }
            }
        }

        return $pairs;
    }

    /**
     * @param string[] $tables
     * @param array<int, array{0: string, 1: string}> $pairs
     * @return array{tables: array<string, array{changes: int, rows: int, seconds: float, note: string}>, changes: int, rows: int}
     */
    public function run(array $tables, array $pairs, bool $dryRun, bool $caseInsensitive = false, bool $guids = false): array
    {
        $report = ['tables' => [], 'changes' => 0, 'rows' => 0];

        foreach ($tables as $table) {
            $started = microtime(true);
            $stats = $this->run_table($table, $pairs, $dryRun, $caseInsensitive, $guids);
            $stats['seconds'] = round(microtime(true) - $started, 3);

            $report['tables'][$table] = $stats;
            $report['changes'] += $stats['changes'];
            $report['rows'] += $stats['rows'];
        }

        return $report;
    }

    /** @return array{changes: int, rows: int, seconds: float, note: string} */
    private function run_table(string $table, array $pairs, bool $dryRun, bool $ci, bool $guids): array
    {
        $db = $this->db;
        $stats = ['changes' => 0, 'rows' => 0, 'seconds' => 0.0, 'note' => ''];
        $quoted = Exporter::ident($table);

        $primary = [];
        $textual = [];
        foreach ((array) $db->get_results("SHOW COLUMNS FROM $quoted", ARRAY_A) as $col) {
            if ($col['Key'] === 'PRI') {
                $primary[] = (string) $col['Field'];
            } elseif (preg_match('/^(char|varchar|tinytext|text|mediumtext|longtext|json)/i', (string) $col['Type'])) {
                if ($col['Field'] === 'guid' && !$guids && str_ends_with($table, 'posts')) {
                    continue;
                }
                $textual[] = (string) $col['Field'];
            }
        }

        if (!$primary) {
            $stats['note'] = 'Pominięto — brak klucza głównego.';
            return $stats;
        }
        if (!$textual) {
            return $stats;
        }

        $select = implode(',', array_map([Exporter::class, 'ident'], array_merge($primary, $textual)));
        $order = implode(',', array_map([Exporter::class, 'ident'], $primary));

        for ($offset = 0;; $offset += self::BATCH) {
            $rows = $db->get_results("SELECT $select FROM $quoted ORDER BY $order LIMIT " . self::BATCH . " OFFSET $offset", ARRAY_A);
            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                $changed = [];
                foreach ($textual as $column) {
                    if ($row[$column] === null || $row[$column] === '') {
                        continue;
                    }

                    $this->found = 0;
                    $new = $this->replace($row[$column], $pairs, $ci);
                    if ($this->found > 0 && $new !== $row[$column]) {
                        $changed[$column] = $new;
                        $stats['changes'] += $this->found;
                    }
                }

                if (!$changed) {
                    continue;
                }

                $stats['rows']++;
                if (!$dryRun) {
                    $where = array_intersect_key($row, array_flip($primary));
                    if ($db->update($table, $changed, $where) === false) {
                        throw new \RuntimeException("Błąd zapisu w tabeli $table: " . $db->last_error);
                    }
                }
            }

            if (count($rows) < self::BATCH) {
                break;
            }
        }

        $db->flush();

        return $stats;
    }

    /**
     * Recursive replace. $this->found counts every replaced occurrence. Untouched values are
     * returned byte for byte, so nothing is re-serialized unless something changed.
     */
    private function replace(mixed $data, array $pairs, bool $ci): mixed
    {
        if (is_string($data)) {
            if (is_serialized($data)) {
                $value = @unserialize($data, ['allowed_classes' => ['stdClass']]);
                if ($value !== false || $data === 'b:0;') {
                    $before = $this->found;
                    $new = $this->replace($value, $pairs, $ci);

                    return $this->found === $before ? $data : serialize($new);
                }
            }

            foreach ($pairs as [$search, $replace]) {
                $data = $ci ? str_ireplace($search, $replace, $data, $n) : str_replace($search, $replace, $data, $n);
                $this->found += $n;
            }

            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->replace($value, $pairs, $ci);
            }

            return $data;
        }

        // Objects of classes that are not loaded cannot be modified; they are left as they are.
        if (is_object($data) && !($data instanceof \__PHP_Incomplete_Class)) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->$key = $this->replace($value, $pairs, $ci);
            }
        }

        return $data;
    }
}
