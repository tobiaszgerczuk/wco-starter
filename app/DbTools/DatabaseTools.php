<?php

namespace WCO\Starter\DbTools;

/**
 * Theme settings > "Baza danych": export, import and search & replace of the database.
 * Admin only (manage_options), nonce protected, and switched off until an administrator turns it on.
 * Hard off switch: define('WCO_DB_TOOLS_OFF', true) in wp-config.php.
 */
final class DatabaseTools
{
    private const PARENT = 'wco-theme-settings';
    private const SLUG = 'wco-db-tools';
    private const ENABLE_OPTION = 'wco_db_tools_enabled';
    private const NONCE = 'wco_db_tools';
    private const MAX_UPLOAD = 1073741824; // 1 GB hard ceiling; the real limit is the server's upload_max_filesize

    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [self::class, 'menu'], 99);
        add_action('admin_post_wco_db_export', [self::class, 'handle_export']);
        add_action('admin_post_wco_db_toggle', [self::class, 'handle_toggle']);
        add_action('admin_post_wco_db_backup_download', [self::class, 'handle_backup_download']);
        add_action('admin_post_wco_db_backup_delete', [self::class, 'handle_backup_delete']);
        foreach (['upload', 'analyze', 'start', 'step'] as $action) {
            add_action('wp_ajax_wco_db_' . $action, [self::class, 'ajax_' . $action]);
        }
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    private static function hard_off(): bool
    {
        return defined('WCO_DB_TOOLS_OFF') && WCO_DB_TOOLS_OFF;
    }

    private static function enabled(): bool
    {
        return !self::hard_off() && get_option(self::ENABLE_OPTION) === 'yes';
    }

    public static function menu(): void
    {
        add_submenu_page(self::PARENT, 'Baza danych', 'Baza danych', 'manage_options', self::SLUG, [self::class, 'render']);
    }

    public static function enqueue(string $hook): void
    {
        if (!str_ends_with($hook, '_' . self::SLUG)) {
            return;
        }
        wp_localize_script('wco-starter-admin', 'WCO_DB', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'home' => home_url(),
            'enabled' => self::enabled(),
            'maxUpload' => wp_max_upload_size(),
        ]);
    }

    // ------------------------------------------------------------------ guards

    private static function guard_post(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer(self::NONCE);
    }

    private static function guard_ajax(bool $needs_enabled = true): void
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(['message' => 'Brak uprawnień lub sesja wygasła. Odśwież stronę.'], 403);
        }
        if ($needs_enabled && !self::enabled()) {
            wp_send_json_error(['message' => 'Narzędzia bazy danych są wyłączone.'], 403);
        }
    }

    // ------------------------------------------------------------------ plain admin-post handlers

    public static function handle_toggle(): void
    {
        self::guard_post();
        if (!self::hard_off()) {
            update_option(self::ENABLE_OPTION, ($_POST['enable'] ?? '') === '1' ? 'yes' : 'no', false);
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }

    public static function handle_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer(self::NONCE);
        if (!self::enabled()) {
            wp_die('Narzędzia bazy danych są wyłączone.', 403);
        }
        SqlExporter::stream_download((string) ($_GET['scope'] ?? 'full'));
        exit;
    }

    private static function backup_file(string $name): ?string
    {
        $name = basename($name);
        if (!preg_match('/^backup-[\w-]+\.sql$/', $name)) {
            return null;
        }
        $path = DbJob::dir() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    public static function handle_backup_download(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer(self::NONCE);
        $path = self::backup_file((string) ($_GET['name'] ?? ''));
        if (!self::enabled() || !$path) {
            wp_die('Nie znaleziono pliku.', 404);
        }
        nocache_headers();
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function handle_backup_delete(): void
    {
        self::guard_post_get();
        $path = self::backup_file((string) ($_GET['name'] ?? ''));
        if ($path) {
            @unlink($path);
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }

    private static function guard_post_get(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer(self::NONCE);
    }

    // ------------------------------------------------------------------ AJAX

    /** @return array<int, array{0:string,1:string}> */
    private static function read_pairs(): array
    {
        $raw = json_decode(wp_unslash((string) ($_POST['pairs'] ?? '[]')), true);
        $pairs = [];
        foreach (is_array($raw) ? $raw : [] as $p) {
            if (is_array($p) && isset($p[0]) && (string) $p[0] !== '') {
                $pairs[] = [(string) $p[0], (string) ($p[1] ?? '')];
            }
        }

        return $pairs;
    }

    public static function ajax_upload(): void
    {
        self::guard_ajax();
        $file = $_FILES['sql'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $codes = [UPLOAD_ERR_INI_SIZE => 'Plik jest większy niż limit serwera (upload_max_filesize).', UPLOAD_ERR_FORM_SIZE => 'Plik jest za duży.'];
            wp_send_json_error(['message' => $codes[$file['error'] ?? 0] ?? 'Nie udało się wczytać pliku.']);
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'sql' || $file['size'] > self::MAX_UPLOAD) {
            wp_send_json_error(['message' => 'Wybierz plik z rozszerzeniem .sql.']);
        }
        $token = bin2hex(random_bytes(12));
        $target = DbJob::upload_path($token);
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            wp_send_json_error(['message' => 'Nie można zapisać pliku na serwerze.']);
        }
        try {
            $analysis = DbJob::analyze($target, [], false);
        } catch (\Throwable $e) {
            @unlink($target);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        wp_send_json_success([
            'token' => $token,
            'name' => sanitize_file_name((string) $file['name']),
            'size' => (int) filesize($target),
            'analysis' => $analysis,
            'current_prefix' => $GLOBALS['wpdb']->prefix,
        ]);
    }

    public static function ajax_analyze(): void
    {
        self::guard_ajax();
        $token = (string) ($_POST['token'] ?? '');
        $path = DbJob::upload_path($token);
        if (!DbJob::valid_id($token) || !is_file($path)) {
            wp_send_json_error(['message' => 'Nie znaleziono pliku. Wczytaj go ponownie.']);
        }
        wp_send_json_success(DbJob::analyze($path, self::read_pairs(), !empty($_POST['ci'])));
    }

    public static function ajax_start(): void
    {
        self::guard_ajax();
        try {
            $mode = ($_POST['mode'] ?? '') === 'import' ? 'import' : 'replace';
            $tables = json_decode(wp_unslash((string) ($_POST['tables'] ?? 'null')), true);
            wp_send_json_success(DbJob::create([
                'mode' => $mode,
                'token' => (string) ($_POST['token'] ?? ''),
                'pairs' => self::read_pairs(),
                'tables' => is_array($tables) ? $tables : null,
                'case_insensitive' => !empty($_POST['ci']),
                'include_guid' => !empty($_POST['guid']),
                'dry' => !empty($_POST['dry']),
                'backup' => !empty($_POST['backup']),
                'keep_user' => !empty($_POST['keep_user']),
                'keep_settings' => !empty($_POST['keep_settings']),
            ]));
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_step(): void
    {
        // no "enabled" check here: a full import replaces the option that stores it
        self::guard_ajax(false);
        try {
            wp_send_json_success(DbJob::step((string) ($_POST['job'] ?? '')));
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ page

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }
        $enabled = self::enabled();
        $nonce = wp_create_nonce(self::NONCE);
        $post_url = esc_url(admin_url('admin-post.php'));
        $export = static fn (string $scope): string => esc_url(wp_nonce_url(admin_url('admin-post.php?action=wco_db_export&scope=' . $scope), self::NONCE));
        $tables = SearchReplace::tables();
        ?>
        <div class="wrap wco-db" id="wco-db-tools">
            <h1>Baza danych: eksport, import, znajdź i zamień</h1>

            <?php if (self::hard_off()) : ?>
                <div class="notice notice-error"><p>Narzędzia są zablokowane na stałe stałą <code>WCO_DB_TOOLS_OFF</code> w <code>wp-config.php</code>.</p></div>
            <?php elseif (!$enabled) : ?>
                <div class="notice notice-warning inline"><p><strong>Narzędzia są wyłączone.</strong> Pozwalają odczytać i nadpisać całą bazę, więc włączaj je tylko na czas pracy i wyłącz po użyciu.</p>
                    <form method="post" action="<?php echo $post_url; ?>">
                        <input type="hidden" name="action" value="wco_db_toggle"><input type="hidden" name="enable" value="1">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                        <p><button class="button button-primary">Włącz narzędzia bazy danych</button></p>
                    </form></div>
            <?php else : ?>
                <div class="notice notice-info inline"><p>Narzędzia są <strong>włączone</strong>. Po skończeniu pracy wyłącz je.
                    <form method="post" action="<?php echo $post_url; ?>" style="display:inline">
                        <input type="hidden" name="action" value="wco_db_toggle"><input type="hidden" name="enable" value="0">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                        <button class="button">Wyłącz</button>
                    </form></p></div>
            <?php endif; ?>

            <?php if ($enabled) : ?>
            <div class="wco-db__grid">
                <section class="wco-db__card">
                    <h2>1. Eksport</h2>
                    <p>Pobierz bazę jako plik <code>.sql</code> (działa bez <code>mysqldump</code>, także na hostingu współdzielonym).</p>
                    <p><a class="button button-primary" href="<?php echo $export('content'); ?>">Pobierz treści i ustawienia</a></p>
                    <p class="description">Strony, wpisy, produkty, media, menu, ustawienia motywu i sklepu. <strong>Bez</strong> użytkowników, zamówień, klientów, komentarzy i kluczy płatności. Plik można bezpiecznie przekazać dalej. Po imporcie na innym serwisie odświeża tylko treści i nie rusza jego zamówień.</p>
                    <p><a class="button" href="<?php echo $export('full'); ?>">Pobierz całą bazę</a></p>
                    <p class="description">Wszystko, łącznie z użytkownikami (hasła w postaci haszy), klientami i zamówieniami. To dane osobowe: nie wysyłaj go przez komunikatory ani do repozytorium.</p>
                </section>

                <section class="wco-db__card" id="wco-import">
                    <h2>2. Import</h2>
                    <p>Wgraj plik <code>.sql</code> (np. z eksportu z drugiego środowiska). Limit serwera: <strong><?php echo esc_html(size_format(wp_max_upload_size())); ?></strong>.</p>
                    <div data-step="pick">
                        <input type="file" accept=".sql" data-file>
                        <p><button type="button" class="button button-primary" data-upload>Wczytaj plik</button></p>
                    </div>
                    <div data-step="options" hidden>
                        <p class="wco-db__file"></p>
                        <table class="widefat striped wco-db__tables" data-analysis-tables hidden></table>
                        <h3>Znajdź i zamień po imporcie</h3>
                        <p class="description">Jak w Better Search Replace: wpisy w bazie są przeszukiwane po imporcie, także dane serializowane (bezpiecznie) i adresy zapisane w JSON (<code>\/</code>). <strong>Wpisuj pełne adresy razem z <code>http://</code> lub <code>https://</code></strong>, np. <code>http://stara-domena.localhost</code> → <code>https://domena.pl</code>. Bez schematu adres zostaje z poprzednim schematem (np. <code>http://</code> zamiast <code>https://</code>).</p>
                        <div data-pairs></div>
                        <p><button type="button" class="button button-small" data-add-pair>+ Dodaj kolejną parę</button></p>
                        <p><label><input type="checkbox" data-opt="ci"> Nie rozróżniaj wielkości liter</label><br>
                           <label><input type="checkbox" data-opt="backup" checked> Zrób pełną kopię zapasową bieżącej bazy przed importem</label><br>
                           <label><input type="checkbox" data-opt="keep_settings" checked> Zachowaj adres witryny, aktywny motyw i wtyczki bieżącej instalacji</label><br>
                           <label><input type="checkbox" data-opt="keep_user" checked> Zachowaj moje konto administratora (bez tego wylogujesz się po imporcie)</label><br>
                           <label><input type="checkbox" data-opt="dry" checked> <strong>Próba (dry run)</strong>: tylko przeanalizuj plik, nic nie zmieniaj</label></p>
                        <p><button type="button" class="button button-primary" data-run-import>Uruchom</button>
                           <button type="button" class="button" data-cancel>Anuluj</button></p>
                    </div>
                    <div data-progress hidden>
                        <div class="wco-db__bar"><span></span></div>
                        <p class="wco-db__msg"></p>
                        <div class="wco-db__result"></div>
                    </div>
                </section>

                <section class="wco-db__card" id="wco-replace">
                    <h2>3. Znajdź i zamień w bieżącej bazie</h2>
                    <p>Zamiana w istniejących tabelach, np. po zmianie domeny. Dane serializowane są obsługiwane bezpiecznie. Wpisuj pełne adresy ze schematem (<code>http://</code> / <code>https://</code>); żeby zmienić tylko schemat, wpisz np. <code>http://domena.pl</code> → <code>https://domena.pl</code>.</p>
                    <div data-pairs></div>
                    <p><button type="button" class="button button-small" data-add-pair>+ Dodaj kolejną parę</button></p>
                    <p><strong>Tabele</strong> <a href="#" data-tables-all>zaznacz wszystkie</a> · <a href="#" data-tables-none>odznacz</a></p>
                    <select multiple size="9" class="wco-db__select" data-tables>
                        <?php foreach ($tables as $t) : ?>
                            <option value="<?php echo esc_attr($t); ?>" selected><?php echo esc_html($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p><label><input type="checkbox" data-opt="ci"> Nie rozróżniaj wielkości liter</label><br>
                       <label><input type="checkbox" data-opt="guid"> Zamieniaj także w kolumnie <code>guid</code> (zwykle nie należy)</label><br>
                       <label><input type="checkbox" data-opt="backup" checked> Zrób pełną kopię zapasową przed zamianą</label><br>
                       <label><input type="checkbox" data-opt="dry" checked> <strong>Próba (dry run)</strong>: pokaż, co zostałoby zmienione</label></p>
                    <p><button type="button" class="button button-primary" data-run-replace>Uruchom</button></p>
                    <div data-progress hidden>
                        <div class="wco-db__bar"><span></span></div>
                        <p class="wco-db__msg"></p>
                        <div class="wco-db__result"></div>
                    </div>
                </section>

                <section class="wco-db__card">
                    <h2>Kopie zapasowe</h2>
                    <?php $backups = DbJob::backups(); ?>
                    <?php if (!$backups) : ?>
                        <p class="description">Brak. Kopie powstają automatycznie przed importem i zamianą (zostaje 10 ostatnich).</p>
                    <?php else : ?>
                        <table class="widefat striped"><tbody>
                        <?php foreach ($backups as $b) : ?>
                            <tr><td><code><?php echo esc_html($b['name']); ?></code></td><td><?php echo esc_html(size_format($b['size'])); ?></td>
                                <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wco_db_backup_download&name=' . rawurlencode($b['name'])), self::NONCE)); ?>">Pobierz</a>
                                    · <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wco_db_backup_delete&name=' . rawurlencode($b['name'])), self::NONCE)); ?>" onclick="return confirm('Usunąć kopię?')">Usuń</a></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <p class="description">Przywrócenie: wczytaj taką kopię w sekcji Import.</p>
                    <?php endif; ?>
                </section>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
