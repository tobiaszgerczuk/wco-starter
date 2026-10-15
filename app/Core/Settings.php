<?php

namespace WCO\Starter\Core;

/**
 * Theme settings: one option (`wco_starter_settings`), one admin page, no plugins required.
 *
 * Values that were saved by the old ACF options page (`options_<name>`) are still read as a
 * fallback, so a site keeps its appearance, tracking and banner settings after the switch.
 */
class Settings
{
    public const OPTION = 'wco_starter_settings';
    public const PAGE = 'wco-theme-settings';
    private const ACTION = 'wco_starter_save_settings';

    /** @var array<string, mixed>|null */
    private static ?array $stored = null;

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_page']);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_head', [self::class, 'print_head_code'], 99);
        add_action('wp_footer', [self::class, 'print_footer_code'], 99);
    }

    // ------------------------------------------------------------------ reading

    public static function get(string $name, mixed $default = null): mixed
    {
        $stored = self::stored();
        if (array_key_exists($name, $stored)) {
            $value = $stored[$name];

            return ($value === null || $value === '') ? $default : $value;
        }

        $legacy = get_option('options_' . $name, null);

        return ($legacy === null || $legacy === false || $legacy === '') ? $default : $legacy;
    }

    /**
     * Fields used by the templates (banner, CTA, social links, fallbacks), with empty defaults.
     *
     * @return array<string, mixed>
     */
    public static function global(): array
    {
        $values = [];
        foreach (self::fields_of(['social', 'banners', 'fallbacks']) as $field) {
            $default = $field['type'] === 'toggle' ? 0 : ($field['type'] === 'link' ? null : '');
            $values[$field['name']] = self::get($field['name'], $default);
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private static function stored(): array
    {
        if (self::$stored === null) {
            $value = get_option(self::OPTION, []);
            self::$stored = is_array($value) ? $value : [];
        }

        return self::$stored;
    }

    public static function print_head_code(): void
    {
        self::print_code('custom_head_code');
    }

    public static function print_footer_code(): void
    {
        self::print_code('custom_footer_code');
    }

    private static function print_code(string $name): void
    {
        $code = self::get($name, '');
        if (is_string($code) && trim($code) !== '') {
            echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-provided code, saved with unfiltered_html.
        }
    }

    // ------------------------------------------------------------------ schema

    /**
     * Tabs and their fields. Types: text, url, textarea, code, toggle, select, number, color, image, link.
     *
     * @return array<string, array{label: string, groups: array<string, array{label: string, fields: list<array<string, mixed>>}>}>
     */
    public static function schema(): array
    {
        $d = Appearance::defaults();
        $color = static fn (string $name, string $label, string $help = ''): array => ['name' => 'appearance_color_' . $name, 'type' => 'color', 'label' => $label, 'help' => $help, 'default' => $d['color_' . $name] ?? ''];

        return [
            'appearance' => ['label' => 'Wygląd', 'groups' => [
                'colors' => ['label' => 'Kolory', 'fields' => [
                    $color('primary', 'Kolor główny', 'Przyciski, linki, wyróżnienia.'),
                    $color('primary_hover', 'Kolor główny po najechaniu', 'Puste = automatycznie ciemniejszy kolor główny.'),
                    $color('secondary', 'Kolor dodatkowy'),
                    $color('accent', 'Kolor akcentu'),
                    $color('text', 'Kolor tekstu'),
                    $color('muted', 'Kolor tekstu pomocniczego', 'Akapity i podpisy.'),
                    $color('bg', 'Tło strony'),
                    $color('surface', 'Kolor powierzchni', 'Karty i jasne sekcje.'),
                    $color('border', 'Kolor ramek'),
                ]],
                'typography' => ['label' => 'Typografia', 'fields' => [
                    ['name' => 'appearance_font_heading', 'type' => 'select', 'label' => 'Czcionka nagłówków', 'options' => Appearance::font_labels(), 'default' => $d['font_heading']],
                    ['name' => 'appearance_font_body', 'type' => 'select', 'label' => 'Czcionka tekstu', 'options' => Appearance::font_labels(), 'default' => $d['font_body']],
                    ['name' => 'appearance_font_size', 'type' => 'number', 'label' => 'Bazowy rozmiar tekstu (px)', 'min' => 14, 'max' => 20, 'default' => $d['font_size']],
                    ['name' => 'appearance_font_local', 'type' => 'toggle', 'label' => 'Hostuj czcionki lokalnie', 'help' => 'Pobiera pliki czcionek raz i serwuje z Twojej strony (bez zapytań do Google, lepiej dla RODO). Przy błędzie pobierania wraca do Google Fonts.', 'default' => 1],
                ]],
                'header' => ['label' => 'Logo i nagłówek', 'fields' => [
                    ['name' => 'appearance_logo', 'type' => 'image', 'label' => 'Logo', 'help' => 'Favicon ustawisz w Wygląd > Dostosuj > Tożsamość witryny.'],
                    ['name' => 'appearance_logo_height', 'type' => 'number', 'label' => 'Wysokość logo (px)', 'min' => 16, 'max' => 160, 'default' => $d['logo_height']],
                    ['name' => 'appearance_header_mode', 'type' => 'select', 'label' => 'Zachowanie nagłówka', 'options' => ['fixed' => 'Przyklejony (zostaje na górze)', 'static' => 'Statyczny (przewija się ze stroną)'], 'default' => $d['header_mode']],
                    ['name' => 'appearance_header_transparent', 'type' => 'toggle', 'label' => 'Przezroczysty na górze strony', 'help' => 'Wyłączone = nagłówek zawsze ma pełne tło.', 'default' => 1],
                    ['name' => 'appearance_header_shrink', 'type' => 'toggle', 'label' => 'Zmniejszaj przy przewijaniu', 'default' => 1],
                ]],
                'shape' => ['label' => 'Kształt', 'fields' => [
                    ['name' => 'appearance_shape', 'type' => 'select', 'label' => 'Zaokrąglenia', 'help' => 'Przyciski, karty i obrazy.', 'options' => ['sharp' => 'Ostre', 'soft' => 'Delikatne', 'default' => 'Domyślne', 'round' => 'Okrągłe'], 'default' => $d['shape']],
                ]],
            ]],
            'tracking' => ['label' => 'Śledzenie i zgody', 'groups' => [
                'consent' => ['label' => 'Cookies i zgody', 'fields' => [
                    ['name' => 'consent_enabled', 'type' => 'toggle', 'label' => 'Baner zgody na cookies', 'help' => 'Pokazywany tylko, gdy ustawiono GTM, Google Analytics lub dodatkowe skrypty. Włącza Google Consent Mode v2 i wstrzymuje dodatkowe skrypty do czasu zgody na analitykę. Wyłącz, jeśli zgodami zarządzasz w GTM.', 'default' => 1],
                    ['name' => 'consent_title', 'type' => 'text', 'label' => 'Tytuł banera', 'default' => 'Szanujemy Twoją prywatność'],
                    ['name' => 'consent_policy_link', 'type' => 'link', 'label' => 'Link do polityki prywatności', 'help' => 'Puste = strona prywatności WordPressa.'],
                    ['name' => 'consent_text', 'type' => 'textarea', 'label' => 'Tekst banera', 'rows' => 4, 'default' => 'Używamy plików cookies, aby strona działała poprawnie, oraz — za Twoją zgodą — do analizy ruchu i działań marketingowych. Możesz zaakceptować wszystkie, odrzucić opcjonalne albo wybrać, na co się zgadzasz.'],
                    ['name' => 'consent_version', 'type' => 'text', 'label' => 'Wersja zgody', 'help' => 'Zmień (np. na 2) po zmianie używanych cookies; każdy odwiedzający zostanie zapytany ponownie.', 'default' => '1'],
                    ['name' => 'tracking_noscript', 'type' => 'toggle', 'label' => 'GTM noscript iframe', 'help' => 'Dodaje <noscript> po <body>. Osoby bez JavaScript nie mogą wyrazić zgody, więc przy ścisłym RODO wyłącz.', 'default' => 1],
                    ['name' => 'tracking_skip_editors', 'type' => 'toggle', 'label' => 'Pomijaj zalogowanych redaktorów', 'help' => 'Twoje wizyty nie zaburzają statystyk.', 'default' => 1],
                ]],
                'tags' => ['label' => 'Skrypty', 'fields' => [
                    ['name' => 'google_tag_manager_id', 'type' => 'text', 'label' => 'Google Tag Manager ID', 'help' => 'Np. GTM-XXXXXX'],
                    ['name' => 'google_analytics_id', 'type' => 'text', 'label' => 'Google Analytics ID', 'help' => 'Identyfikator pomiaru GA4, np. G-XXXXXX'],
                    ['name' => 'tracking_head_scripts', 'type' => 'code', 'label' => 'Dodatkowe skrypty w <head>', 'help' => 'Ładowane po zgodzie na analitykę, gdy baner jest włączony.', 'rows' => 6],
                    ['name' => 'tracking_body_scripts', 'type' => 'code', 'label' => 'Dodatkowe skrypty przed </body>', 'rows' => 6],
                    ['name' => 'custom_head_code', 'type' => 'code', 'label' => 'Własny kod w <head>', 'help' => 'Dodawany zawsze, bez bramki zgody.', 'rows' => 6],
                    ['name' => 'custom_footer_code', 'type' => 'code', 'label' => 'Własny kod przed </body>', 'rows' => 6],
                ]],
            ]],
            'social' => ['label' => 'Social i SEO', 'groups' => [
                'social' => ['label' => 'Social media', 'fields' => [
                    ['name' => 'social_facebook_url', 'type' => 'url', 'label' => 'Facebook'],
                    ['name' => 'social_instagram_url', 'type' => 'url', 'label' => 'Instagram'],
                    ['name' => 'social_linkedin_url', 'type' => 'url', 'label' => 'LinkedIn'],
                    ['name' => 'social_x_url', 'type' => 'url', 'label' => 'X / Twitter'],
                ]],
                'meta' => ['label' => 'Domyślne meta', 'fields' => [
                    ['name' => 'default_meta_title', 'type' => 'text', 'label' => 'Domyślny tytuł meta'],
                    ['name' => 'default_meta_description', 'type' => 'textarea', 'label' => 'Domyślny opis meta', 'rows' => 3],
                ]],
            ]],
            'banners' => ['label' => 'Banery i CTA', 'groups' => [
                'banner' => ['label' => 'Baner globalny', 'fields' => [
                    ['name' => 'global_banner_enabled', 'type' => 'toggle', 'label' => 'Pokaż baner', 'default' => 0],
                    ['name' => 'global_banner_html', 'type' => 'code', 'label' => 'Treść / HTML banera', 'rows' => 4],
                ]],
                'cta' => ['label' => 'Globalne CTA', 'fields' => [
                    ['name' => 'global_cta_enabled', 'type' => 'toggle', 'label' => 'Pokaż CTA', 'default' => 0],
                    ['name' => 'global_cta_title', 'type' => 'text', 'label' => 'Tytuł CTA'],
                    ['name' => 'global_cta_text', 'type' => 'textarea', 'label' => 'Tekst CTA', 'rows' => 3],
                    ['name' => 'global_cta_link', 'type' => 'link', 'label' => 'Link CTA'],
                ]],
            ]],
            'fallbacks' => ['label' => 'Treści zastępcze', 'groups' => [
                'home' => ['label' => 'Strona główna', 'fields' => [
                    ['name' => 'fallback_home_title', 'type' => 'text', 'label' => 'Tytuł zastępczy'],
                    ['name' => 'fallback_home_content', 'type' => 'textarea', 'label' => 'Treść zastępcza', 'rows' => 4],
                ]],
                'notfound' => ['label' => 'Błąd 404', 'fields' => [
                    ['name' => 'fallback_404_title', 'type' => 'text', 'label' => 'Tytuł 404'],
                    ['name' => 'fallback_404_message', 'type' => 'textarea', 'label' => 'Komunikat 404', 'rows' => 3],
                ]],
            ]],
        ];
    }

    /**
     * @param list<string> $tabs
     * @return list<array<string, mixed>>
     */
    private static function fields_of(array $tabs): array
    {
        $fields = [];
        foreach (self::schema() as $key => $tab) {
            if (!in_array($key, $tabs, true)) {
                continue;
            }
            foreach ($tab['groups'] as $group) {
                array_push($fields, ...$group['fields']);
            }
        }

        return $fields;
    }

    // ------------------------------------------------------------------ admin page

    public static function register_page(): void
    {
        add_menu_page('Ustawienia motywu', 'Ustawienia motywu', 'manage_options', self::PAGE, [self::class, 'render_page'], 'dashicons-admin-generic', 59);
    }

    public static function enqueue(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_style('wp-color-picker', '.wco-settings .wco-field{display:grid;grid-template-columns:260px minmax(0,640px);gap:8px 24px;padding:14px 0;border-bottom:1px solid #f0f0f1}.wco-settings .wco-field>label,.wco-settings .wco-field>span.wco-label{font-weight:600}.wco-settings .description{margin-top:4px}.wco-settings textarea,.wco-settings input[type=text],.wco-settings input[type=url],.wco-settings input[type=number],.wco-settings select{width:100%}.wco-settings .wco-link{display:grid;gap:6px}.wco-settings h2.wco-group{margin:28px 0 0;font-size:15px}.wco-settings .wco-logo img{max-width:200px;height:auto;display:block;margin-bottom:8px}.wco-settings textarea.code{font-family:ui-monospace,monospace;font-size:12px}@media(max-width:782px){.wco-settings .wco-field{grid-template-columns:1fr}}');
        wp_add_inline_script('wp-color-picker', <<<'JS'
jQuery(function ($) {
  $('.wco-color').wpColorPicker();
  $('.wco-logo').each(function () {
    var box = $(this), input = box.find('input[type=hidden]'), preview = box.find('.wco-logo__preview');
    box.on('click', '.wco-logo__pick', function (e) {
      e.preventDefault();
      var frame = wp.media({ library: { type: 'image' }, multiple: false });
      frame.on('select', function () {
        var item = frame.state().get('selection').first().toJSON();
        input.val(item.id);
        preview.html($('<img>').attr('src', (item.sizes && item.sizes.medium ? item.sizes.medium.url : item.url)));
      });
      frame.open();
    });
    box.on('click', '.wco-logo__remove', function (e) { e.preventDefault(); input.val(''); preview.empty(); });
  });
});
JS);
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $schema = self::schema();
        $tab = isset($_GET['tab']) && isset($schema[sanitize_key((string) $_GET['tab'])]) ? sanitize_key((string) $_GET['tab']) : array_key_first($schema); // phpcs:ignore WordPress.Security.NonceVerification
        $base = admin_url('admin.php?page=' . self::PAGE);

        echo '<div class="wrap wco-settings"><h1>Ustawienia motywu</h1>';
        if (isset($_GET['updated'])) { // phpcs:ignore WordPress.Security.NonceVerification
            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zapisane.</p></div>';
        }

        echo '<nav class="nav-tab-wrapper">';
        foreach ($schema as $key => $item) {
            printf('<a class="nav-tab%s" href="%s">%s</a>', $key === $tab ? ' nav-tab-active' : '', esc_url(add_query_arg('tab', $key, $base)), esc_html($item['label']));
        }
        echo '</nav>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '">';

        foreach ($schema[$tab]['groups'] as $group) {
            echo '<h2 class="wco-group">' . esc_html($group['label']) . '</h2>';
            foreach ($group['fields'] as $field) {
                self::render_field($field);
            }
        }

        submit_button('Zapisz ustawienia');
        echo '</form></div>';
    }

    /** @param array<string, mixed> $field */
    private static function render_field(array $field): void
    {
        $name = $field['name'];
        $id = 'wco-' . $name;
        $value = self::get($name, $field['default'] ?? '');
        $input = 'wco[' . $name . ']';
        $help = !empty($field['help']) ? '<p class="description">' . esc_html($field['help']) . '</p>' : '';

        echo '<div class="wco-field"><label for="' . esc_attr($id) . '"><span class="wco-label">' . esc_html($field['label']) . '</span></label><div>';

        switch ($field['type']) {
            case 'toggle':
                printf('<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1"%3$s> Włączone</label>', esc_attr($input), esc_attr($id), checked((string) $value === '1' || $value === true, true, false));
                break;
            case 'select':
                printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($input));
                foreach ($field['options'] as $key => $label) {
                    printf('<option value="%s"%s>%s</option>', esc_attr((string) $key), selected((string) $value, (string) $key, false), esc_html($label));
                }
                echo '</select>';
                break;
            case 'number':
                printf('<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="1">', esc_attr($id), esc_attr($input), esc_attr((string) $value), esc_attr((string) ($field['min'] ?? '')), esc_attr((string) ($field['max'] ?? '')));
                break;
            case 'color':
                $default = (string) ($field['default'] ?? '');
                printf('<input type="text" class="wco-color" id="%s" name="%s" value="%s" data-default-color="%s">', esc_attr($id), esc_attr($input), esc_attr(is_string($value) ? $value : ''), esc_attr($default));
                break;
            case 'image':
                $imageId = (int) $value;
                echo '<div class="wco-logo"><div class="wco-logo__preview">' . ($imageId ? wp_get_attachment_image($imageId, 'medium') : '') . '</div>';
                printf('<input type="hidden" name="%s" value="%s"><button type="button" class="button wco-logo__pick">Wybierz obraz</button> <button type="button" class="button-link wco-logo__remove">Usuń</button></div>', esc_attr($input), esc_attr($imageId ?: ''));
                break;
            case 'link':
                $link = is_array($value) ? $value : [];
                printf('<div class="wco-link"><input type="url" name="%1$s[url]" value="%2$s" placeholder="https://"><input type="text" name="%1$s[title]" value="%3$s" placeholder="Tekst linku"><label><input type="checkbox" name="%1$s[target]" value="_blank"%4$s> Otwórz w nowej karcie</label></div>', esc_attr($input), esc_attr((string) ($link['url'] ?? '')), esc_attr((string) ($link['title'] ?? '')), checked(($link['target'] ?? '') === '_blank', true, false));
                break;
            case 'textarea':
            case 'code':
                printf('<textarea id="%s" name="%s" rows="%d"%s>%s</textarea>', esc_attr($id), esc_attr($input), (int) ($field['rows'] ?? 4), $field['type'] === 'code' ? ' class="code" spellcheck="false"' : '', esc_textarea(is_string($value) ? $value : ''));
                break;
            default:
                printf('<input type="%s" id="%s" name="%s" value="%s">', $field['type'] === 'url' ? 'url' : 'text', esc_attr($id), esc_attr($input), esc_attr(is_string($value) ? $value : ''));
        }

        echo $help . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
    }

    // ------------------------------------------------------------------ saving

    public static function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $schema = self::schema();
        $tab = sanitize_key((string) ($_POST['tab'] ?? ''));
        if (!isset($schema[$tab])) {
            wp_die('Nieznana zakładka.', '', ['response' => 400]);
        }

        $input = isset($_POST['wco']) && is_array($_POST['wco']) ? wp_unslash($_POST['wco']) : [];
        $stored = self::stored();

        foreach (self::fields_of([$tab]) as $field) {
            $stored[$field['name']] = self::sanitize($field, $input[$field['name']] ?? null);
        }

        update_option(self::OPTION, $stored, false);
        self::$stored = $stored;

        do_action('wco_starter_settings_saved', $stored, $tab);

        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'tab' => $tab, 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    /** @param array<string, mixed> $field */
    private static function sanitize(array $field, mixed $raw): mixed
    {
        switch ($field['type']) {
            case 'toggle':
                return $raw === '1' ? '1' : '0';
            case 'select':
                return is_string($raw) && isset($field['options'][$raw]) ? $raw : (string) ($field['default'] ?? '');
            case 'number':
                $number = (int) $raw;

                return (string) max((int) ($field['min'] ?? PHP_INT_MIN), min((int) ($field['max'] ?? PHP_INT_MAX), $number));
            case 'color':
                $color = is_string($raw) ? trim($raw) : '';

                return preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color) === 1 ? strtolower($color) : '';
            case 'image':
                return (string) absint($raw);
            case 'url':
                return is_string($raw) ? esc_url_raw($raw) : '';
            case 'link':
                $link = is_array($raw) ? $raw : [];
                $url = esc_url_raw((string) ($link['url'] ?? ''));

                return $url === '' ? '' : ['url' => $url, 'title' => sanitize_text_field((string) ($link['title'] ?? '')), 'target' => ($link['target'] ?? '') === '_blank' ? '_blank' : ''];
            case 'code':
                $code = is_string($raw) ? $raw : '';

                return current_user_can('unfiltered_html') ? $code : wp_kses_post($code);
            case 'textarea':
                return is_string($raw) ? sanitize_textarea_field($raw) : '';
            default:
                return is_string($raw) ? sanitize_text_field($raw) : '';
        }
    }
}
