<?php

namespace WCO\Starter\Core;

/**
 * Optional HTTP Basic Auth gate for the whole site (the "htpasswd" style protection used on staging
 * and before a launch). Configured in Theme settings > Site protection.
 *
 * It runs in PHP, so it needs no server configuration, but it also means a page cache or CDN that
 * serves pages without starting WordPress will bypass it. The password is stored as a hash.
 */
class Protection
{
    private const PAGE = 'wco-theme-settings';
    private const OPT = 'options_protection_';

    public static function boot(): void
    {
        add_action('init', [self::class, 'enforce'], 1);
        add_action('acf/init', [self::class, 'register_fields'], 20);
        add_filter('acf/load_value/name=protection_password', '__return_empty_string');
        add_filter('acf/update_value/name=protection_password', [self::class, 'hash_password'], 10, 1);
        add_filter('acf/validate_value/name=protection_password', [self::class, 'validate_password'], 10, 4);
        add_action('admin_bar_menu', [self::class, 'admin_bar'], 100);
    }

    // ------------------------------------------------------------------ settings (raw options, no ACF needed)

    private static function enabled(): bool
    {
        return (string) get_option(self::OPT . 'enabled') === '1';
    }

    private static function user(): string
    {
        return (string) get_option(self::OPT . 'user', '');
    }

    private static function hash(): string
    {
        return (string) get_option(self::OPT . 'hash', '');
    }

    /** Protection only applies when it is switched on AND a user name and password exist. */
    private static function configured(): bool
    {
        return self::user() !== '' && self::hash() !== '';
    }

    /** @return string[] */
    private static function allowed_ips(): array
    {
        $raw = (string) get_option(self::OPT . 'allowed_ips', '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $raw) ?: [])));
    }

    // ------------------------------------------------------------------ enforcement

    public static function enforce(): void
    {
        if (!self::enabled() || !self::configured() || self::is_exempt()) {
            return;
        }

        [$user, $password] = self::credentials();
        if ($user !== null && hash_equals(self::user(), $user) && password_verify((string) $password, self::hash())) {
            return;
        }

        // Slows down guessing; a legitimate visitor only pays for it after a typo.
        if ($user !== null) {
            sleep(1);
        }

        self::challenge();
    }

    private static function is_exempt(): bool
    {
        if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
            return true;
        }

        $script = basename((string) parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
        // The WordPress login and cron endpoints stay reachable so administrators can sign in.
        if (in_array($script, ['wp-login.php', 'wp-cron.php'], true)) {
            return true;
        }
        // wp-admin pages already require a WordPress login; admin-ajax.php and admin-post.php are
        // public endpoints and stay protected.
        if (is_admin() && !wp_doing_ajax() && $script !== 'admin-post.php') {
            return true;
        }

        if ((string) get_option(self::OPT . 'skip_logged_in', '1') === '1' && is_user_logged_in()) {
            return true;
        }

        // REMOTE_ADDR only: forwarded-for headers can be forged by the visitor.
        return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), self::allowed_ips(), true);
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function credentials(): array
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return [(string) $_SERVER['PHP_AUTH_USER'], (string) ($_SERVER['PHP_AUTH_PW'] ?? '')];
        }

        // PHP-FPM / CGI setups pass the header only as HTTP_AUTHORIZATION.
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($header, 'basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$user, $password] = explode(':', $decoded, 2);

                return [$user, $password];
            }
        }

        return [null, null];
    }

    private static function challenge(): void
    {
        $realm = trim((string) get_option(self::OPT . 'message', '')) ?: 'Strona chroniona hasłem';
        $realm = str_replace(['"', "\r", "\n"], '', $realm);

        nocache_headers();
        status_header(401);
        header('WWW-Authenticate: Basic realm="' . $realm . '", charset="UTF-8"');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>401</title></head><body><h1>401</h1><p>Ta strona jest chroniona hasłem.</p></body></html>';
        exit;
    }

    // ------------------------------------------------------------------ admin

    private static function t(string $en, string $pl): string
    {
        return str_starts_with(strtolower(get_locale()), 'pl') ? $pl : $en;
    }

    /** Shows a lock in the admin bar so nobody forgets the protection is on. */
    public static function admin_bar($bar): void
    {
        if (!self::enabled() || !self::configured() || !current_user_can('manage_options')) {
            return;
        }

        $bar->add_node([
            'id' => 'wco-protection',
            'title' => '🔒 ' . self::t('Site protected', 'Strona chroniona hasłem'),
            'href' => admin_url('admin.php?page=' . self::PAGE),
            'meta' => ['title' => self::t('HTTP password protection is on', 'Włączone jest zabezpieczenie hasłem HTTP')],
        ]);
    }

    /**
     * Stores the password as a hash. An empty field keeps the current one; the field is always
     * shown empty (see the acf/load_value filter), so the hash never reaches the browser.
     *
     * @param mixed $value
     */
    public static function hash_password($value): string
    {
        $value = is_string($value) ? $value : '';
        if ($value === '') {
            return '';
        }

        update_option(self::OPT . 'hash', password_hash($value, PASSWORD_DEFAULT), false);

        return '';
    }

    /**
     * @param mixed  $valid
     * @param mixed  $value
     * @param array  $field
     * @param string $input
     * @return mixed
     */
    public static function validate_password($valid, $value, $field, $input)
    {
        $enabled = $_POST['acf']['field_protection_enabled'] ?? '0'; // phpcs:ignore WordPress.Security.NonceVerification -- ACF verifies the nonce before validating.
        if ($valid === true && (string) $enabled === '1' && (string) $value === '' && self::hash() === '') {
            return self::t('Set a password before turning the protection on.', 'Ustaw hasło, zanim włączysz zabezpieczenie.');
        }

        return $valid;
    }

    public static function register_fields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $field = static fn (string $name, string $label, string $type, array $extra = []): array => array_merge([
            'key' => 'field_protection_' . $name,
            'label' => $label,
            'name' => 'protection_' . $name,
            'type' => $type,
            'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
        ], $extra);

        acf_add_local_field_group([
            'key' => 'group_wco_protection',
            'title' => 'Site protection',
            'fields' => [
                $field('enabled', 'Password-protect the site (HTTP authentication)', 'true_false', [
                    'ui' => 1,
                    'default_value' => 0,
                    'instructions' => 'Visitors must enter the user name and password below before any page loads, like an .htpasswd file. Use it on staging or before launch.',
                ]),
                $field('user', 'User name', 'text', [
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                    'autocomplete' => 'off',
                ]),
                $field('password', 'Password', 'password', [
                    'instructions' => 'Stored as a hash. Leave empty to keep the current password.',
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                ]),
                $field('skip_logged_in', 'Do not ask logged-in WordPress users', 'true_false', [
                    'ui' => 1,
                    'default_value' => 1,
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                ]),
                $field('message', 'Prompt message', 'text', [
                    'default_value' => 'Strona chroniona hasłem',
                    'instructions' => 'Shown in the browser sign-in dialog.',
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                ]),
                $field('allowed_ips', 'Allowed IP addresses', 'textarea', [
                    'rows' => 3,
                    'new_lines' => '',
                    'instructions' => 'Optional. Addresses (one per line or comma separated) that skip the password, e.g. your office. The WordPress login page always stays reachable. A page cache or CDN that answers without starting WordPress bypasses this protection.',
                ]),
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => self::PAGE]]],
            'menu_order' => -20,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);
    }
}
