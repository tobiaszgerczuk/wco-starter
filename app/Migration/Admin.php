<?php

namespace WCO\Starter\Migration;

/**
 * Theme settings > "Eksport / import bazy": database export, import and search and replace.
 * Import and search and replace share one form layout modelled on Better Search Replace
 * (search for / replace with / tables / case-insensitive / GUIDs / dry run).
 */
class Admin
{
    private const PARENT = 'wco-theme-settings';
    private const SLUG = 'wco-db-tools';
    private const TABS = ['export' => 'Eksport', 'import' => 'Import', 'replace' => 'Znajdź i zamień'];

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'menu'], 100);
        add_action('admin_post_wco_db_export', [self::class, 'handle_export']);
    }

    public static function menu(): void
    {
        $title = 'Eksport / import bazy';
        $args = [$title, $title, 'manage_options', self::SLUG, [self::class, 'render']];

        if (function_exists('acf_add_options_page')) {
            add_submenu_page(self::PARENT, ...$args);
        } else {
            add_management_page(...$args);
        }
    }

    private static function allowed(): bool
    {
        return current_user_can('manage_options') && (!is_multisite() || is_super_admin());
    }

    private static function url(string $tab): string
    {
        return add_query_arg(['page' => self::SLUG, 'tab' => $tab], admin_url(function_exists('acf_add_options_page') ? 'admin.php' : 'tools.php'));
    }

    // ------------------------------------------------------------------ export (file download)

    public static function handle_export(): void
    {
        if (!self::allowed()) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer('wco_db_export');

        global $wpdb;
        $exporter = new Exporter($wpdb);
        $tables = array_values(array_intersect($exporter->tables(), array_map('sanitize_text_field', wp_unslash((array) ($_POST['tables'] ?? [])))));
        if (!$tables) {
            wp_die('Wybierz przynajmniej jedną tabelę.', 400);
        }

        @set_time_limit(0);
        $gzip = !empty($_POST['gzip']) && function_exists('deflate_init');
        $context = $gzip ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]) : null;

        nocache_headers();
        header('Content-Type: ' . ($gzip ? 'application/gzip' : 'application/sql') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . Exporter::filename($gzip) . '"');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $exporter->export(static function (string $piece) use ($context): void {
            echo $context ? deflate_add($context, $piece, ZLIB_NO_FLUSH) : $piece;
            flush();
        }, $tables, !empty($_POST['skip_transients']));

        if ($context) {
            echo deflate_add($context, '', ZLIB_FINISH);
        }
        exit;
    }

    // ------------------------------------------------------------------ page

    public static function render(): void
    {
        if (!self::allowed()) {
            wp_die('Brak uprawnień.', 403);
        }

        $tab = isset($_GET['tab'], self::TABS[$_GET['tab']]) ? (string) $_GET['tab'] : 'export';
        $outcome = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($tab, ['import', 'replace'], true)) {
            check_admin_referer('wco_db_' . $tab);
            @set_time_limit(0);
            wp_raise_memory_limit('admin');

            try {
                $outcome = $tab === 'import' ? self::process_import() : self::process_replace();
            } catch (\Throwable $e) {
                $outcome = ['error' => $e->getMessage()];
            }
        }

        echo '<div class="wrap"><h1>Eksport i import bazy danych</h1><nav class="nav-tab-wrapper">';
        foreach (self::TABS as $key => $label) {
            printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url(self::url($key)), $key === $tab ? ' nav-tab-active' : '', esc_html($label));
        }
        echo '</nav>';

        if ($outcome) {
            self::render_outcome($outcome, $tab);
        }

        global $wpdb;
        $tables = (new Exporter($wpdb))->tables();
        echo '<div style="max-width:880px;margin-top:16px">';
        match ($tab) {
            'import' => self::form_import($tables),
            'replace' => self::form_replace($tables),
            default => self::form_export($tables),
        };
        echo '</div>';
        self::script();
        echo '</div>';
    }

    // ------------------------------------------------------------------ actions

    /** @return array<string, mixed> */
    private static function process_import(): array
    {
        $file = $_FILES['wco_dump'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'Nie udało się wgrać pliku (sprawdź limit wielkości: ' . size_format(wp_max_upload_size()) . ').'];
        }
        if (!preg_match('/\.(sql|gz)$/i', (string) $file['name'])) {
            return ['error' => 'Obsługiwane są pliki .sql oraz .sql.gz.'];
        }

        $dry = !empty($_POST['dry_run']);
        if (!$dry && empty($_POST['confirm'])) {
            return ['error' => 'Zaznacz potwierdzenie — import nadpisze tabele w bazie.'];
        }

        global $wpdb;
        $backup = null;
        if (!$dry && !empty($_POST['backup'])) {
            $backup = (new Exporter($wpdb))->backup('before-import');
        }

        $result = (new Importer($wpdb))->run($file['tmp_name'], [
            'dry_run' => $dry,
            'search' => (string) wp_unslash($_POST['search'] ?? ''),
            'replace' => (string) wp_unslash($_POST['replace'] ?? ''),
            'case_insensitive' => !empty($_POST['case_insensitive']),
            'guids' => !empty($_POST['guids']),
            'keep_urls' => !empty($_POST['keep_urls']),
        ]);

        return ['import' => $result, 'dry' => $dry, 'backup' => $backup];
    }

    /** @return array<string, mixed> */
    private static function process_replace(): array
    {
        global $wpdb;
        $pairs = SearchReplace::pairs((string) wp_unslash($_POST['search'] ?? ''), (string) wp_unslash($_POST['replace'] ?? ''));
        if (!$pairs) {
            return ['error' => 'Wpisz, czego szukać (i na co zamienić — wartość inna niż szukana).'];
        }

        $tables = array_values(array_intersect((new Exporter($wpdb))->tables(), array_map('sanitize_text_field', wp_unslash((array) ($_POST['tables'] ?? [])))));
        if (!$tables) {
            return ['error' => 'Wybierz przynajmniej jedną tabelę.'];
        }

        $dry = !empty($_POST['dry_run']);
        $report = (new SearchReplace($wpdb))->run($tables, $pairs, $dry, !empty($_POST['case_insensitive']), !empty($_POST['guids']));
        if (!$dry) {
            wp_cache_flush();
        }

        return ['replace' => $report, 'dry' => $dry];
    }

    // ------------------------------------------------------------------ output

    /** @param array<string, mixed> $o */
    private static function render_outcome(array $o, string $tab): void
    {
        if (isset($o['error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($o['error']) . '</p></div>';
            return;
        }

        $dry = !empty($o['dry']);

        if (isset($o['import'])) {
            $r = $o['import'];
            $tables = $r['tables'];
            $text = $dry
                ? 'Test (dry run): plik wczytano do tabel tymczasowych i usunięto je — baza nie została zmieniona.'
                : 'Import zakończony: wykonano ' . (int) $r['statements'] . ' poleceń, tabel: ' . count($tables) . '. Zapisz bezpośrednie odnośniki (Ustawienia → Bezpośrednie odnośniki), jeśli adresy podstron nie działają.';
            echo '<div class="notice notice-success"><p>' . esc_html($text) . '</p></div>';

            if (!empty($o['backup'])) {
                echo '<div class="notice notice-info"><p>Kopia sprzed importu: <code>' . esc_html($o['backup']) . '</code> (możesz ją wgrać tym samym formularzem).</p></div>';
            }
            if ($r['foreign']) {
                echo '<div class="notice notice-warning"><p>Pominięto tabele z innym prefiksem niż <code>' . esc_html($GLOBALS['wpdb']->prefix) . '</code>: ' . esc_html(implode(', ', $r['foreign'])) . '.</p></div>';
            }
            if ($r['skipped']) {
                echo '<div class="notice notice-warning"><p>Pominięto niedozwolone polecenia SQL (np. <code>' . esc_html($r['skipped'][0]) . '</code>) — importer wykonuje tylko tworzenie tabel i wstawianie danych.</p></div>';
            }

            $source = untrailingslashit((string) ($r['source']['siteurl'] ?? ''));
            $current = untrailingslashit(site_url());
            if ($source !== '' && $source !== $current) {
                $lines = [$source];
                $home = untrailingslashit((string) ($r['source']['home'] ?? ''));
                if ($home !== '' && $home !== $source) {
                    $lines[] = $home;
                }
                $replace = untrailingslashit(home_url());
                printf(
                    '<div class="notice notice-info"><p>Adres w pliku: <code>%s</code>, adres tej witryny: <code>%s</code>. %s <button type="button" class="button" data-wco-fill data-search="%s" data-replace="%s">Wstaw do pól „Szukaj” i „Zamień na”</button></p></div>',
                    esc_html($source),
                    esc_html($current),
                    $r['replaced'] ? '' : 'Bez zamiany adresów w treści strony będą wskazywać stary adres.',
                    esc_attr(implode("\n", $lines)),
                    esc_attr($replace)
                );
            }

            self::report($tables, true, $dry);
        } elseif (isset($o['replace'])) {
            $text = $dry ? 'Test (dry run): nic nie zostało zapisane. Poniżej to, co zostałoby zmienione.' : 'Zamiana zakończona.';
            echo '<div class="notice notice-success"><p>' . esc_html($text) . '</p></div>';
            self::report($o['replace']['tables'], false, $dry);
        }
    }

    /** @param array<string, array<string, mixed>> $tables */
    private static function report(array $tables, bool $inserted, bool $dry): void
    {
        echo '<table class="widefat striped" style="margin:12px 0"><thead><tr><th>Tabela</th>';
        echo $inserted ? '<th>Wstawione wiersze</th>' : '';
        echo '<th>Znalezione zmiany</th><th>' . ($dry ? 'Wiersze do zmiany' : 'Zaktualizowane wiersze') . '</th><th>Czas</th></tr></thead><tbody>';

        $changes = $rows = $ins = 0;
        foreach ($tables as $name => $t) {
            $changes += $t['changes'];
            $rows += $t['rows'];
            $ins += $t['inserted'] ?? 0;
            printf(
                '<tr><td>%s%s</td>%s<td>%d</td><td>%d</td><td>%s s</td></tr>',
                esc_html($name),
                $t['note'] ? '<br><em>' . esc_html($t['note']) . '</em>' : '',
                $inserted ? '<td>' . (int) ($t['inserted'] ?? 0) . '</td>' : '',
                $t['changes'],
                $t['rows'],
                esc_html((string) $t['seconds'])
            );
        }

        printf(
            '<tr><td><strong>Razem</strong></td>%s<td><strong>%d</strong></td><td><strong>%d</strong></td><td></td></tr></tbody></table>',
            $inserted ? '<td><strong>' . $ins . '</strong></td>' : '',
            $changes,
            $rows
        );
    }

    // ------------------------------------------------------------------ forms

    private static function val(string $key): string
    {
        return isset($_POST[$key]) ? esc_textarea((string) wp_unslash($_POST[$key])) : '';
    }

    private static function checked(string $key, bool $default): string
    {
        $on = $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST[$key]) : $default;

        return $on ? ' checked' : '';
    }

    /** @param string[] $tables */
    private static function table_picker(array $tables, string $note = ''): void
    {
        echo '<div style="max-height:200px;overflow:auto;border:1px solid #c3c4c7;background:#fff;padding:8px 12px;columns:2">';
        $posted = isset($_POST['tables']) ? (array) wp_unslash($_POST['tables']) : null;
        foreach ($tables as $table) {
            $on = $posted === null || in_array($table, $posted, true);
            printf('<label style="display:block"><input type="checkbox" name="tables[]" value="%s"%s> %s</label>', esc_attr($table), $on ? ' checked' : '', esc_html($table));
        }
        echo '</div><p><a href="#" data-wco-toggle>Zaznacz / odznacz wszystkie</a>' . ($note ? ' · ' . esc_html($note) : '') . '</p>';
    }

    private static function replace_fields(): void
    {
        echo '<tr><th scope="row"><label for="search">Szukaj</label></th><td><textarea id="search" name="search" rows="3" class="large-text code" placeholder="https://stara-domena.pl">' . self::val('search') . '</textarea>'
            . '<p class="description">Jedna wartość na linię.</p></td></tr>';
        echo '<tr><th scope="row"><label for="replace">Zamień na</label></th><td><textarea id="replace" name="replace" rows="3" class="large-text code" placeholder="http://nowa-templatka.localhost">' . self::val('replace') . '</textarea>'
            . '<p class="description">Linia po linii: pierwsza wartość zastępuje pierwszą szukaną itd. Jedna linia zastępuje wszystkie. Dane serializowane są obsługiwane, a adresy z ukośnikami także w postaci JSON (<code>https:\/\/</code>).</p></td></tr>';
    }

    private static function form_export(array $tables): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wco_db_export');
        echo '<input type="hidden" name="action" value="wco_db_export">';
        echo '<p>Plik SQL zawiera strukturę i dane wybranych tabel. Wgrasz go później na innej instalacji w zakładce Import.</p><table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Tabele</th><td>';
        self::table_picker($tables);
        echo '</td></tr><tr><th scope="row">Opcje</th><td>';
        echo '<label><input type="checkbox" name="skip_transients" value="1" checked> Pomiń transienty (pamięć podręczna w tabeli opcji)</label><br>';
        if (function_exists('deflate_init')) {
            echo '<label><input type="checkbox" name="gzip" value="1" checked> Skompresuj (.sql.gz)</label>';
        }
        echo '</td></tr></table>';
        submit_button('Pobierz plik SQL');
        echo '</form>';
    }

    private static function form_import(array $tables): void
    {
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(self::url('import')) . '">';
        wp_nonce_field('wco_db_import');
        echo '<div class="notice notice-warning inline"><p><strong>Uwaga:</strong> import nadpisuje tabele z pliku (łącznie z użytkownikami). Po imporcie możesz zostać wylogowany — zaloguj się danymi z importowanej bazy. Zacznij od testu (dry run).</p></div>';
        echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="wco_dump">Plik SQL</label></th><td>';
        echo '<input type="file" id="wco_dump" name="wco_dump" accept=".sql,.gz" required>';
        echo '<p class="description">.sql lub .sql.gz, maks. ' . esc_html(size_format(wp_max_upload_size())) . '. Importowane są tylko tabele z prefiksem <code>' . esc_html($GLOBALS['wpdb']->prefix) . '</code>.</p></td></tr>';
        self::replace_fields();
        echo '<tr><th scope="row">Opcje</th><td>';
        echo '<label><input type="checkbox" name="case_insensitive" value="1"' . self::checked('case_insensitive', false) . '> Ignoruj wielkość liter</label><br>';
        echo '<label><input type="checkbox" name="guids" value="1"' . self::checked('guids', false) . '> Zamień także GUID-y wpisów (zwykle nie zaleca się)</label><br>';
        echo '<label><input type="checkbox" name="keep_urls" value="1"' . self::checked('keep_urls', true) . '> Zachowaj adresy tej witryny (<code>siteurl</code> i <code>home</code>) po imporcie</label><br>';
        echo '<label><input type="checkbox" name="backup" value="1"' . self::checked('backup', true) . '> Zrób kopię bazy przed importem (zapis w <code>uploads/wco-db-backups</code>)</label><br>';
        echo '<label><input type="checkbox" name="dry_run" value="1"' . self::checked('dry_run', true) . '> <strong>Test (dry run)</strong> — nic nie zmieniaj, pokaż tylko co by się stało</label><br>';
        echo '<label><input type="checkbox" name="confirm" value="1"> Rozumiem, że import nadpisze tabele (wymagane bez testu)</label>';
        echo '</td></tr></table>';
        submit_button('Importuj');
        echo '</form>';
    }

    private static function form_replace(array $tables): void
    {
        echo '<form method="post" action="' . esc_url(self::url('replace')) . '">';
        wp_nonce_field('wco_db_replace');
        echo '<table class="form-table" role="presentation">';
        self::replace_fields();
        echo '<tr><th scope="row">Tabele</th><td>';
        self::table_picker($tables, 'Tabele bez klucza głównego są pomijane.');
        echo '</td></tr><tr><th scope="row">Opcje</th><td>';
        echo '<label><input type="checkbox" name="case_insensitive" value="1"' . self::checked('case_insensitive', false) . '> Ignoruj wielkość liter</label><br>';
        echo '<label><input type="checkbox" name="guids" value="1"' . self::checked('guids', false) . '> Zamień także GUID-y wpisów (zwykle nie zaleca się)</label><br>';
        echo '<label><input type="checkbox" name="dry_run" value="1"' . self::checked('dry_run', true) . '> <strong>Test (dry run)</strong> — nic nie zapisuj, pokaż tylko liczbę zmian</label>';
        echo '</td></tr></table>';
        submit_button('Wykonaj');
        echo '</form>';
    }

    private static function script(): void
    {
        echo <<<'HTML'
<script>
document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-wco-toggle]');
    if (toggle) {
        e.preventDefault();
        var boxes = toggle.closest('td').querySelectorAll('input[name="tables[]"]');
        var on = !Array.prototype.every.call(boxes, function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = on; });
    }
    var fill = e.target.closest('[data-wco-fill]');
    if (fill) {
        document.getElementById('search').value = fill.dataset.search;
        document.getElementById('replace').value = fill.dataset.replace;
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
});
</script>
HTML;
    }
}
