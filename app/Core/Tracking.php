<?php

namespace WCO\Starter\Core;

use Timber\Timber;

/**
 * Tracking and cookie consent: Google Tag Manager (head snippet + <noscript> after <body>),
 * GA4, Google Consent Mode v2, the cookie banner and consent-gated custom scripts.
 *
 * With the banner enabled, every Google consent type starts as "denied" (or as the visitor's
 * stored choice) before GTM loads, and the "Additional scripts" fields are only injected after the
 * visitor accepts analytics cookies. With the banner disabled nothing is changed or gated, which
 * suits sites that manage consent in GTM with their own CMP.
 */
class Tracking
{
    private const PAGE = 'wco-theme-settings';
    private const COOKIE = 'wco_consent';

    public static function boot(): void
    {
        add_action('acf/init', [self::class, 'register_fields'], 20);
        add_action('wp_head', [self::class, 'print_head'], 2);
        add_action('wp_body_open', [self::class, 'print_noscript'], 1);
        add_action('wp_footer', [self::class, 'print_footer'], 5);
        add_filter('timber/context', [self::class, 'add_to_context']);
    }

    // ------------------------------------------------------------------ settings

    private static function option(string $name, $default = null)
    {
        if (!function_exists('get_field')) {
            return $default;
        }

        $value = get_field($name, 'option');

        return ($value === null || $value === '') ? $default : $value;
    }

    private static function gtm_id(): string
    {
        $id = strtoupper(trim((string) self::option('google_tag_manager_id', '')));

        return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) === 1 ? $id : '';
    }

    private static function ga4_id(): string
    {
        $id = strtoupper(trim((string) self::option('google_analytics_id', '')));

        return preg_match('/^G-[A-Z0-9]{4,14}$/', $id) === 1 ? $id : '';
    }

    private static function custom_scripts(string $field): string
    {
        return trim((string) self::option($field, ''));
    }

    private static function has_tracking(): bool
    {
        return self::gtm_id() !== ''
            || self::ga4_id() !== ''
            || self::custom_scripts('tracking_head_scripts') !== ''
            || self::custom_scripts('tracking_body_scripts') !== '';
    }

    /** Tracking is skipped for logged-in editors so their visits do not pollute the statistics. */
    private static function tracking_enabled(): bool
    {
        if (self::option('tracking_skip_editors', true) && function_exists('current_user_can') && current_user_can('edit_posts')) {
            return false;
        }

        return !is_customize_preview();
    }

    public static function consent_enabled(): bool
    {
        return (bool) self::option('consent_enabled', true) && self::has_tracking();
    }

    private static function consent_version(): string
    {
        $version = preg_replace('/[^A-Za-z0-9_-]/', '', (string) self::option('consent_version', '1'));

        return $version !== '' && $version !== null ? $version : '1';
    }

    // ------------------------------------------------------------------ head, body, footer

    public static function print_head(): void
    {
        if (!self::tracking_enabled()) {
            return;
        }

        $consent = self::consent_enabled();
        if ($consent) {
            echo self::consent_default_script(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from validated values.
        }

        $gtm = self::gtm_id();
        if ($gtm !== '') {
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($gtm) . "');</script>\n";
        }

        $ga4 = self::ga4_id();
        if ($ga4 !== '') {
            echo '<script async src="' . esc_url('https://www.googletagmanager.com/gtag/js?id=' . $ga4) . "\"></script>\n";
            echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js($ga4) . "');</script>\n";
        }

        self::print_custom_scripts('tracking_head_scripts', 'head', $consent);
    }

    public static function print_noscript(): void
    {
        $gtm = self::gtm_id();
        if ($gtm === '' || !self::tracking_enabled() || !self::option('tracking_noscript', true)) {
            return;
        }

        echo '<noscript><iframe src="' . esc_url('https://www.googletagmanager.com/ns.html?id=' . $gtm) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
    }

    public static function print_footer(): void
    {
        if (self::consent_enabled() && !is_customize_preview()) {
            echo Timber::compile('partials/cookie-banner.twig', ['consent' => self::banner_context()]);
        }

        if (self::tracking_enabled()) {
            self::print_custom_scripts('tracking_body_scripts', 'body', self::consent_enabled());
        }
    }

    /**
     * Raw admin-provided code. With consent enabled it is parked in a <template> and injected by
     * assets/js/modules/consent.js after analytics consent; otherwise it is printed as is.
     */
    private static function print_custom_scripts(string $field, string $target, bool $gated): void
    {
        $code = self::custom_scripts($field);
        if ($code === '') {
            return;
        }

        if (!$gated) {
            echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted: only administrators can edit theme settings.
            return;
        }

        echo '<template data-wco-consent="analytics" data-target="' . esc_attr($target) . '">' . $code . "</template>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- see above.
    }

    /**
     * Consent Mode v2 defaults, restored from the visitor's cookie so a returning visitor does not
     * start from "denied". Cookie format: <version>.a<0|1>.m<0|1>.
     */
    private static function consent_default_script(): string
    {
        $version = wp_json_encode(self::consent_version());

        return "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
            . "(function(){var m=document.cookie.match(/(?:^|; )" . self::COOKIE . "=([^;]*)/),p=m?m[1].split('.'):[],ok=p[0]===" . $version . ",a=ok&&p[1]==='a1'?'granted':'denied',k=ok&&p[2]==='m1'?'granted':'denied';"
            . "gtag('consent','default',{ad_storage:k,ad_user_data:k,ad_personalization:k,analytics_storage:a,functionality_storage:'granted',security_storage:'granted',wait_for_update:500});"
            . "gtag('set','ads_data_redaction',true);})();</script>\n";
    }

    /** @return array<string, mixed> */
    private static function banner_context(): array
    {
        $link = self::option('consent_policy_link');
        $policy = is_array($link) && !empty($link['url']) ? (string) $link['url'] : (string) (get_privacy_policy_url() ?: '/polityka-prywatnosci');

        return [
            'version' => self::consent_version(),
            'cookie' => self::COOKIE,
            'title' => esc_html((string) self::option('consent_title', 'Szanujemy Twoją prywatność')),
            'text' => wp_kses(
                (string) self::option('consent_text', 'Używamy plików cookies, aby strona działała poprawnie, oraz — za Twoją zgodą — do analizy ruchu i działań marketingowych. Możesz zaakceptować wszystkie, odrzucić opcjonalne albo wybrać, na co się zgadzasz.'),
                ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => [], 'em' => [], 'br' => []]
            ),
            'policy_url' => esc_url($policy),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function add_to_context(array $context): array
    {
        $context['consent'] = ['enabled' => self::consent_enabled()];

        return $context;
    }

    // ------------------------------------------------------------------ admin fields

    public static function register_fields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $field = static fn (string $name, string $label, string $type, array $extra = []): array => array_merge([
            'key' => 'field_tracking_' . $name,
            'label' => $label,
            'name' => $name,
            'type' => $type,
            'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
        ], $extra);

        acf_add_local_field_group([
            'key' => 'group_wco_consent',
            'title' => 'Cookies and consent',
            'fields' => [
                $field('consent_enabled', 'Cookie consent banner', 'true_false', [
                    'ui' => 1,
                    'default_value' => 1,
                    'instructions' => 'Shown only when Google Tag Manager, Google Analytics or additional scripts are set. Turns on Google Consent Mode v2 (everything denied until the visitor agrees) and holds back the additional scripts until analytics cookies are accepted. Turn it off if you manage consent inside GTM.',
                ]),
                $field('consent_title', 'Banner title', 'text', [
                    'default_value' => 'Szanujemy Twoją prywatność',
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                ]),
                $field('consent_policy_link', 'Privacy policy link', 'link', [
                    'return_format' => 'array',
                    'instructions' => 'Leave empty to use the WordPress privacy page.',
                    'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
                ]),
                $field('consent_text', 'Banner text', 'textarea', [
                    'rows' => 4,
                    'new_lines' => '',
                    'default_value' => 'Używamy plików cookies, aby strona działała poprawnie, oraz — za Twoją zgodą — do analizy ruchu i działań marketingowych. Możesz zaakceptować wszystkie, odrzucić opcjonalne albo wybrać, na co się zgadzasz.',
                ]),
                $field('consent_version', 'Consent version', 'text', [
                    'default_value' => '1',
                    'instructions' => 'Change it (e.g. to 2) after changing which cookies you use; every visitor is then asked again.',
                    'wrapper' => ['width' => '33', 'class' => '', 'id' => ''],
                ]),
                $field('tracking_noscript', 'GTM noscript iframe', 'true_false', [
                    'ui' => 1,
                    'default_value' => 1,
                    'instructions' => 'Adds the <noscript> iframe after <body>. Visitors without JavaScript cannot give consent, so turn it off for strict GDPR setups.',
                    'wrapper' => ['width' => '33', 'class' => '', 'id' => ''],
                ]),
                $field('tracking_skip_editors', 'Skip tracking for logged-in editors', 'true_false', [
                    'ui' => 1,
                    'default_value' => 1,
                    'instructions' => 'Keeps your own visits out of the statistics.',
                    'wrapper' => ['width' => '33', 'class' => '', 'id' => ''],
                ]),
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => self::PAGE]]],
            'menu_order' => -5,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);
    }
}
