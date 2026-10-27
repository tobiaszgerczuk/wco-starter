<?php

namespace WCO\Starter\DbTools;

/**
 * Writes the database as SQL, in pure PHP (works on shared hosting without mysqldump).
 *
 * Scopes:
 *  - full:    every table with the site prefix (CREATE + INSERT), including users and orders.
 *  - content: only pages, posts, products, media records, menus, terms, forms and an allow-list of
 *             options; no users, orders, customers, comments, sessions or payment keys. The file
 *             first deletes what it owns, so loading it refreshes the content of another site
 *             without touching that site's customers and orders.
 */
final class SqlExporter
{
    public const SCOPES = ['full', 'content'];

    private const POST_TYPES = ['page', 'post', 'product', 'product_variation', 'attachment', 'nav_menu_item', 'wp_block',
        'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wpcf7_contact_form', 'shop_coupon'];

    /** Options included in the "content" scope (LIKE patterns). */
    private const OPTION_PATTERNS = [
        'blogname', 'blogdescription', 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'site_icon',
        'timezone_string', 'date_format', 'time_format', 'start_of_week', 'WPLANG', 'posts_per_page', 'default_category',
        'default_product_cat', 'theme_mods\_%', 'nav_menu_options',
        'options\_%', '\_options\_%',
        'woocommerce\_%\_page\_id', 'woocommerce\_currency%', 'woocommerce\_price\_%', 'woocommerce\_default\_country',
        'woocommerce\_calc\_taxes', 'woocommerce\_prices\_include\_tax', 'woocommerce\_enable\_%', 'woocommerce\_permalinks',
        'woocommerce\_downloads\_%', 'woocommerce\_file\_download\_method', 'woocommerce\_email\_from\_%',
        'woocommerce\_coming\_soon', 'woocommerce\_store\_pages\_only', 'woocommerce\_manage\_stock',
        'woocommerce\_bacs\_settings', 'woocommerce\_cheque\_settings', 'woocommerce\_cod\_settings',
        'woocommerce\_p24-%', 'p24\_installments\_%',
    ];

    /** Auto-increment ids that must not travel in the "content" scope (they could clash on the target). */
    private const SKIP_COLUMNS = ['options' => ['option_id'], 'postmeta' => ['meta_id'], 'termmeta' => ['meta_id']];

    /** @param callable(string):void $out */
    public static function write(callable $out, string $scope = 'full'): void
    {
        global $wpdb;

        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'full';
        }

        $out("-- Database export\n");
        $out('-- scope: ' . $scope . "\n");
        $out('-- site: ' . home_url() . "\n");
        $out('-- prefix: ' . $wpdb->prefix . "\n");
        $out('-- generated: ' . gmdate('Y-m-d H:i:s') . " UTC\n\n");
        $out("SET NAMES utf8mb4;\nSET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        if ($scope === 'full') {
            foreach (self::tables() as $table) {
                self::dump_table($out, $table);
            }
        } else {
            self::dump_content($out);
        }

        $out("\nSET FOREIGN_KEY_CHECKS=1;\n");
    }

    public static function to_file(string $path, string $scope = 'full'): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Nie można zapisać pliku kopii.');
        }
        self::write(static function (string $chunk) use ($fh): void {
            fwrite($fh, $chunk);
        }, $scope);
        fclose($fh);
    }

    public static function stream_download(string $scope): void
    {
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'full';
        $name = sanitize_file_name(sprintf('%s-%s-%s.sql', sanitize_title(get_bloginfo('name')) ?: 'baza', $scope, gmdate('Ymd-His')));

        @set_time_limit(0);
        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');

        self::write(static function (string $chunk): void {
            echo $chunk;
            flush();
        }, $scope);
    }

    /** @return string[] */
    public static function tables(): array
    {
        global $wpdb;

        $like = $wpdb->esc_like($wpdb->prefix) . '%';

        return array_map('strval', $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like)) ?: []);
    }

    private static function value(?string $v, bool $binary): string
    {
        global $wpdb;

        if ($v === null) {
            return 'NULL';
        }
        if ($binary && $v !== '') {
            return '0x' . bin2hex($v);
        }

        return "'" . $wpdb->_real_escape($v) . "'";
    }

    /** @param callable(string):void $out */
    private static function dump_table(callable $out, string $table): void
    {
        global $wpdb;

        $create = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
        if (!$create) {
            return;
        }
        $out("-- Table `$table`\nDROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n");
        self::dump_rows($out, $table, '1=1', $table);
        $out("\n");
    }

    /** @param callable(string):void $out */
    private static function dump_rows(callable $out, string $table, string $where, string $target, array $skip = []): void
    {
        global $wpdb;

        $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A) ?: [];
        $binary = [];
        $pk = [];
        foreach ($columns as $c) {
            $binary[$c['Field']] = (bool) preg_match('/blob|binary|bit/i', $c['Type']);
            if ($c['Key'] === 'PRI') {
                $pk[] = '`' . $c['Field'] . '`';
            }
        }
        $order = $pk ? ' ORDER BY ' . implode(',', $pk) : '';
        $batch = 300;

        for ($offset = 0; ; $offset += $batch) {
            $rows = $wpdb->get_results("SELECT * FROM `$table` WHERE $where$order LIMIT $batch OFFSET $offset", ARRAY_A);
            if (!$rows) {
                break;
            }
            if ($skip) {
                $rows = array_map(static fn (array $r): array => array_diff_key($r, array_flip($skip)), $rows);
            }
            $cols = implode(',', array_map(static fn ($c) => "`$c`", array_keys($rows[0])));
            $values = [];
            foreach ($rows as $row) {
                $cells = [];
                foreach ($row as $col => $val) {
                    $cells[] = self::value($val === null ? null : (string) $val, $binary[$col] ?? false);
                }
                $values[] = '(' . implode(',', $cells) . ')';
            }
            $out("INSERT INTO `$target` ($cols) VALUES\n" . implode(",\n", $values) . ";\n");
            if (count($rows) < $batch) {
                break;
            }
        }
    }

    /** @param callable(string):void $out */
    private static function dump_content(callable $out): void
    {
        global $wpdb;

        $p = $wpdb->prefix;
        $types = implode(',', array_map(static fn ($t) => "'" . esc_sql($t) . "'", self::POST_TYPES));
        $posts_where = "post_type IN ($types) AND post_status NOT IN ('trash','auto-draft')";
        $post_ids = "SELECT ID FROM {$p}posts WHERE $posts_where";
        $options_where = '(' . implode(' OR ', array_map(static fn ($pat) => "option_name LIKE '" . esc_sql($pat) . "'", self::OPTION_PATTERNS)) . ')';

        $out("-- Removes what this snapshot owns, then loads it. Users, orders and customers are untouched.\n");
        $out("DELETE tr FROM `{$p}term_relationships` tr JOIN `{$p}posts` po ON po.ID = tr.object_id WHERE po.post_type IN ($types);\n");
        $out("DELETE pm FROM `{$p}postmeta` pm JOIN `{$p}posts` po ON po.ID = pm.post_id WHERE po.post_type IN ($types);\n");
        $out("DELETE FROM `{$p}posts` WHERE post_type IN ($types);\n");
        $out("DELETE FROM `{$p}termmeta`;\nDELETE FROM `{$p}term_taxonomy`;\nDELETE FROM `{$p}terms`;\n");
        $out("DELETE FROM `{$p}options` WHERE $options_where;\n\n");

        $plan = [
            'posts' => $posts_where,
            'postmeta' => "post_id IN ($post_ids) AND meta_key NOT IN ('_edit_lock','_edit_last')",
            'term_relationships' => "object_id IN ($post_ids)",
            'terms' => '1=1',
            'term_taxonomy' => '1=1',
            'termmeta' => '1=1',
            'options' => $options_where,
        ];
        foreach ($plan as $name => $where) {
            self::dump_rows($out, $p . $name, $where, $p . $name, self::SKIP_COLUMNS[$name] ?? []);
        }
    }
}
