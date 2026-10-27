<?php

namespace WCO\Starter\DbTools;

/**
 * Serialization-safe search & replace over database tables (the same idea as Better Search Replace).
 *
 * - Serialized PHP values are unserialized, replaced recursively and serialized again, so string
 *   lengths stay correct. Objects of unknown classes are never rebuilt; such cells are skipped and
 *   reported.
 * - Each pair that contains "/" is also applied in its JSON-escaped form ("\/"), because block
 *   comments in post content store URLs that way.
 * - Dry run counts what would change and writes nothing.
 */
final class SearchReplace
{
    private const BATCH = 200;

    /**
     * @param array<int, array{0:string,1:string}> $pairs
     * @return array<int, array{0:string,1:string}>
     */
    public static function expand_pairs(array $pairs): array
    {
        $out = [];
        foreach ($pairs as [$search, $replace]) {
            $search = (string) $search;
            if ($search === '') {
                continue;
            }
            $out[] = [$search, (string) $replace];
            if (str_contains($search, '/')) {
                $out[] = [str_replace('/', '\/', $search), str_replace('/', '\/', (string) $replace)];
            }
        }

        return $out;
    }

    /** @return string[] */
    public static function tables(): array
    {
        return SqlExporter::tables();
    }

    /**
     * Processes one table within the time budget.
     *
     * @param array<int, array{0:string,1:string}> $pairs already expanded
     * @param int $cursor row offset to continue from (updated)
     * @return array{done:bool, rows:int, cells:int, skipped:int, note:string}
     */
    public static function process_table(string $table, array $pairs, bool $dry, bool $case_insensitive, bool $include_guid, int &$cursor, float $deadline): array
    {
        global $wpdb;

        $result = ['done' => false, 'rows' => 0, 'cells' => 0, 'skipped' => 0, 'note' => ''];

        $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A) ?: [];
        $pk = [];
        $text = [];
        foreach ($columns as $c) {
            if ($c['Key'] === 'PRI') {
                $pk[] = $c['Field'];
            }
            if (preg_match('/char|text|json/i', $c['Type']) && ($include_guid || $c['Field'] !== 'guid')) {
                $text[] = $c['Field'];
            }
        }
        if (!$pk) {
            $result['done'] = true;
            $result['note'] = 'pominięta: brak klucza głównego';

            return $result;
        }
        if (!$text) {
            $result['done'] = true;

            return $result;
        }

        $order = implode(',', array_map(static fn ($f) => "`$f`", $pk));

        while (microtime(true) < $deadline) {
            $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY $order LIMIT " . self::BATCH . ' OFFSET ' . (int) $cursor, ARRAY_A);
            if (!$rows) {
                $result['done'] = true;
                break;
            }
            foreach ($rows as $row) {
                $changes = [];
                foreach ($text as $col) {
                    $value = $row[$col];
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $count = 0;
                    $skipped = false;
                    $new = self::replace_in($value, $pairs, $case_insensitive, $count, $skipped);
                    if ($skipped) {
                        $result['skipped']++;
                        continue;
                    }
                    if ($count > 0 && $new !== $value) {
                        $changes[$col] = $new;
                        $result['cells']++;
                    }
                }
                if ($changes) {
                    $result['rows']++;
                    if (!$dry) {
                        $where = [];
                        foreach ($pk as $f) {
                            $where[$f] = $row[$f];
                        }
                        $wpdb->update($table, $changes, $where);
                    }
                }
            }
            $cursor += count($rows);
            if (count($rows) < self::BATCH) {
                $result['done'] = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Replaces in any value; serialized strings are handled structurally.
     *
     * @param array<int, array{0:string,1:string}> $pairs
     */
    public static function replace_in(string $value, array $pairs, bool $ci, int &$count, bool &$skipped): string
    {
        if (is_serialized($value)) {
            $data = @unserialize($value, ['allowed_classes' => false]);
            if ($data !== false || $value === 'b:0;') {
                if (self::has_incomplete_object($data)) {
                    $skipped = true;

                    return $value;
                }
                $n = 0;
                $new = self::walk($data, $pairs, $ci, $n);
                $count += $n;

                return $n > 0 ? serialize($new) : $value;
            }
        }

        return self::replace_string($value, $pairs, $ci, $count);
    }

    /** @param array<int, array{0:string,1:string}> $pairs */
    private static function walk($data, array $pairs, bool $ci, int &$count)
    {
        if (is_string($data)) {
            // a serialized value can be nested inside another one
            if (is_serialized($data)) {
                $inner = @unserialize($data, ['allowed_classes' => false]);
                if ($inner !== false && !self::has_incomplete_object($inner)) {
                    $n = 0;
                    $new = self::walk($inner, $pairs, $ci, $n);
                    $count += $n;

                    return $n > 0 ? serialize($new) : $data;
                }
            }

            return self::replace_string($data, $pairs, $ci, $count);
        }
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $key = is_string($k) ? self::replace_string($k, $pairs, $ci, $count) : $k;
                $out[$key] = self::walk($v, $pairs, $ci, $count);
            }

            return $out;
        }
        if (is_object($data)) {
            foreach (get_object_vars($data) as $k => $v) {
                $data->$k = self::walk($v, $pairs, $ci, $count);
            }
        }

        return $data;
    }

    private static function has_incomplete_object($data): bool
    {
        if ($data instanceof \__PHP_Incomplete_Class) {
            return true;
        }
        if (is_array($data)) {
            foreach ($data as $v) {
                if (self::has_incomplete_object($v)) {
                    return true;
                }
            }
        }
        if (is_object($data)) {
            foreach (get_object_vars($data) as $v) {
                if (self::has_incomplete_object($v)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, array{0:string,1:string}> $pairs */
    private static function replace_string(string $s, array $pairs, bool $ci, int &$count): string
    {
        foreach ($pairs as [$search, $replace]) {
            if ($ci) {
                $n = 0;
                $new = preg_replace_callback('/' . preg_quote($search, '/') . '/iu', static fn () => $replace, $s, -1, $n);
                if ($new !== null) { // null = invalid UTF-8: leave the value alone rather than lose it
                    $s = $new;
                    $count += $n;
                }
            } else {
                $n = 0;
                $s = str_replace($search, $replace, $s, $n);
                $count += $n;
            }
        }

        return $s;
    }
}
