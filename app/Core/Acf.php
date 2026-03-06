<?php

namespace WCO\Starter\Core;

class Acf
{
    private const FIELD_KEY_PREFIX = 'field_wco_';

    private const GLOBAL_SETTINGS_DEFAULTS = [
        'tracking_head_scripts' => '',
        'tracking_body_scripts' => '',
        'google_tag_manager_id' => '',
        'google_analytics_id' => '',
        'social_facebook_url' => '',
        'social_instagram_url' => '',
        'social_linkedin_url' => '',
        'social_x_url' => '',
        'default_meta_title' => '',
        'default_meta_description' => '',
        'fallback_home_title' => '',
        'fallback_home_content' => '',
        'fallback_404_title' => '',
        'fallback_404_message' => '',
        'global_banner_enabled' => 0,
        'global_banner_html' => '',
        'global_cta_enabled' => 0,
        'global_cta_title' => '',
        'global_cta_text' => '',
        'global_cta_link' => null,
    ];

    public static function boot(): void
    {
        add_action('acf/init', [self::class, 'register_options_page']);
        add_action('wp_head', [self::class, 'render_custom_head_code'], 99);
        add_action('wp_footer', [self::class, 'render_custom_footer_code'], 99);
        add_filter('acf/load_field', [self::class, 'translate_field']);
        add_filter('acf/load_field_group', [self::class, 'translate_field_group']);
        add_action('acf/update_field_group', [self::class, 'sync_block_json_to_file']);
    }

    public static function sync_block_json_to_file(array $fieldGroup): void
    {
        $blockName = self::resolve_block_name_from_group($fieldGroup);
        if ($blockName === null) {
            return;
        }

        $blockDir = get_template_directory() . '/views/blocks/' . $blockName;
        if (!is_dir($blockDir)) {
            return;
        }

        $jsonPath = $blockDir . '/group_' . $blockName . '.json';
        $payload = json_encode($fieldGroup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        file_put_contents($jsonPath, $payload . "\n");
    }

    public static function save_json(string $path): string
    {
        $path = self::json_dir();
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        return $path;
    }

    public static function load_json(array $paths): array
    {
        $paths[] = self::json_dir();

        $blocksDir = get_template_directory() . '/views/blocks';
        if (is_dir($blocksDir)) {
            $blockPaths = glob($blocksDir . '/*', GLOB_ONLYDIR) ?: [];
            $paths = array_merge($paths, $blockPaths);
        }

        return array_values(array_unique($paths));
    }

    public static function get_global_settings(): array
    {
        if (!function_exists('get_fields')) {
            return self::GLOBAL_SETTINGS_DEFAULTS;
        }

        $raw = get_fields('option');
        if (!is_array($raw)) {
            return self::GLOBAL_SETTINGS_DEFAULTS;
        }

        return wp_parse_args($raw, self::GLOBAL_SETTINGS_DEFAULTS);
    }

    public static function field_group_sync_status(): array
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_field_group')) {
            return [];
        }

        $localGroups = self::local_field_groups();
        if ($localGroups === []) {
            return [];
        }

        $dbGroups = acf_get_field_groups();
        $dbGroupMap = [];
        foreach ($dbGroups as $group) {
            if (!empty($group['key'])) {
                $dbGroupMap[$group['key']] = $group;
            }
        }

        $status = [
            'missing_in_db' => [],
            'missing_in_local' => [],
            'local_ahead' => [],
            'db_ahead' => [],
        ];

        foreach ($localGroups as $key => $localGroup) {
            if (!isset($dbGroupMap[$key])) {
                $status['missing_in_db'][] = $localGroup['title'];
                continue;
            }

            $dbGroup = $dbGroupMap[$key];
            $dbModified = (int) ($dbGroup['modified'] ?? 0);
            $localModified = (int) ($localGroup['modified'] ?? 0);

            if ($dbModified > 0 && $localModified > 0) {
                $delta = abs($localModified - $dbModified);
                if ($delta > 60) {
                    if ($localModified > $dbModified) {
                        $status['local_ahead'][] = $localGroup['title'];
                    } else {
                        $status['db_ahead'][] = $localGroup['title'];
                    }
                }
            }
        }

        foreach ($dbGroupMap as $key => $dbGroup) {
            if (!isset($localGroups[$key])) {
                $status['missing_in_local'][] = $dbGroup['title'] ?? $key;
            }
        }

        return array_filter($status, static fn(array $items): bool => !empty($items));
    }

    private static function local_field_groups(): array
    {
        $result = [];
        $jsonDirs = [self::json_dir()];

        $blocksDir = get_template_directory() . '/views/blocks';
        if (is_dir($blocksDir)) {
            $blockDirs = glob($blocksDir . '/*', GLOB_ONLYDIR) ?: [];
            $jsonDirs = array_merge($jsonDirs, $blockDirs);
        }

        foreach (self::collect_group_json_files($jsonDirs) as $data) {
            $result[$data['key']] = [
                'path' => $data['path'],
                'title' => $data['title'],
                'modified' => $data['modified'],
            ];
        }

        return $result;
    }

    private static function collect_group_json_files(array $directories): array
    {
        $groups = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . '/group_*.json') ?: [];
            foreach ($files as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    continue;
                }

                $data = json_decode($contents, true);
                if (!is_array($data) || empty($data['key'])) {
                    continue;
                }

                $groups[$data['key']] = [
                    'path' => $file,
                    'title' => $data['title'] ?? basename($file),
                    'modified' => (int) ($data['modified'] ?? filemtime($file)),
                ];
            }
        }

        return $groups;
    }

    private static function resolve_block_name_from_group(array $fieldGroup): ?string
    {
        $metadata = $fieldGroup['wco_metadata'] ?? null;
        if (is_array($metadata) && !empty($metadata['block_name']) && is_string($metadata['block_name'])) {
            return sanitize_title($metadata['block_name']);
        }

        if (!empty($fieldGroup['key']) && is_string($fieldGroup['key']) && preg_match('/^group_block_(.+)$/', $fieldGroup['key'], $matches) === 1) {
            return sanitize_title($matches[1]);
        }

        if (!empty($fieldGroup['location'][0][0]['param']) && $fieldGroup['location'][0][0]['param'] === 'block') {
            $value = $fieldGroup['location'][0][0]['value'] ?? '';
            if (is_string($value) && str_starts_with($value, 'acf/')) {
                return sanitize_title(substr($value, 4));
            }
        }

        return null;
    }

    private static function json_dir(): string
    {
        return get_template_directory() . '/acf-json';
    }

    public static function register_options_page(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        acf_add_options_page([
            'page_title' => self::translate_string('Theme settings'),
            'menu_title' => self::translate_string('Theme settings'),
            'menu_slug'  => 'wco-theme-settings',
            'capability' => 'manage_options',
            'redirect'   => false,
            'position'   => 59,
            'icon_url'   => 'dashicons-admin-generic',
        ]);

        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        $fields = [
            [
                'key' => self::FIELD_KEY_PREFIX . 'custom_code_tab',
                'label' => self::translate_string('Custom code'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'custom_head_code',
                'label' => self::translate_string('Custom code in <head>'),
                'name' => 'custom_head_code',
                'type' => 'textarea',
                'instructions' => self::translate_string('Code added before the closing </head> tag.'),
                'rows' => 8,
                'new_lines' => '',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'custom_footer_code',
                'label' => self::translate_string('Custom code before </body>'),
                'name' => 'custom_footer_code',
                'type' => 'textarea',
                'instructions' => self::translate_string('Code added before the closing </body> tag.'),
                'rows' => 8,
                'new_lines' => '',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'tracking_tab',
                'label' => self::translate_string('Tracking'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'google_tag_manager_id',
                'label' => self::translate_string('Google Tag Manager ID'),
                'name' => 'google_tag_manager_id',
                'type' => 'text',
                'instructions' => self::translate_string('Paste GTM ID for automatic injection e.g. GTM-XXXXXX'),
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'google_analytics_id',
                'label' => self::translate_string('Google Analytics ID'),
                'name' => 'google_analytics_id',
                'type' => 'text',
                'instructions' => self::translate_string('Paste GA4 measurement ID, e.g. G-XXXXXX'),
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'tracking_head_scripts',
                'label' => self::translate_string('Additional scripts in <head>'),
                'name' => 'tracking_head_scripts',
                'type' => 'textarea',
                'instructions' => self::translate_string('Additional tracking scripts added in <head>.'),
                'rows' => 6,
                'new_lines' => '',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'tracking_body_scripts',
                'label' => self::translate_string('Additional scripts before </body>'),
                'name' => 'tracking_body_scripts',
                'type' => 'textarea',
                'instructions' => self::translate_string('Additional tracking scripts added before </body>.'),
                'rows' => 6,
                'new_lines' => '',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'social_meta_tab',
                'label' => self::translate_string('Social & Meta'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'social_facebook_url',
                'label' => self::translate_string('Facebook URL'),
                'name' => 'social_facebook_url',
                'type' => 'url',
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'social_instagram_url',
                'label' => self::translate_string('Instagram URL'),
                'name' => 'social_instagram_url',
                'type' => 'url',
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'social_linkedin_url',
                'label' => self::translate_string('LinkedIn URL'),
                'name' => 'social_linkedin_url',
                'type' => 'url',
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'social_x_url',
                'label' => self::translate_string('X / Twitter URL'),
                'name' => 'social_x_url',
                'type' => 'url',
                'wrapper' => ['width' => '50', 'class' => '', 'id' => ''],
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'default_meta_title',
                'label' => self::translate_string('Default meta title'),
                'name' => 'default_meta_title',
                'type' => 'text',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'default_meta_description',
                'label' => self::translate_string('Default meta description'),
                'name' => 'default_meta_description',
                'type' => 'textarea',
                'rows' => 4,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'banners_cta_tab',
                'label' => self::translate_string('Banners and CTA'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_banner_enabled',
                'label' => self::translate_string('Global banner'),
                'name' => 'global_banner_enabled',
                'type' => 'true_false',
                'message' => self::translate_string('Use global banner'),
                'default_value' => 0,
                'ui' => 1,
                'ui_on_text' => 'On',
                'ui_off_text' => 'Off',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_banner_html',
                'label' => self::translate_string('Banner code / HTML'),
                'name' => 'global_banner_html',
                'type' => 'textarea',
                'instructions' => self::translate_string('Shown at the top of every page.'),
                'rows' => 6,
                'new_lines' => '',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_cta_enabled',
                'label' => self::translate_string('Global CTA'),
                'name' => 'global_cta_enabled',
                'type' => 'true_false',
                'message' => self::translate_string('Enable global CTA section'),
                'default_value' => 0,
                'ui' => 1,
                'ui_on_text' => 'On',
                'ui_off_text' => 'Off',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_cta_title',
                'label' => self::translate_string('CTA title'),
                'name' => 'global_cta_title',
                'type' => 'text',
                'instructions' => self::translate_string('Title for fallback CTA section.'),
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_cta_text',
                'label' => self::translate_string('CTA text'),
                'name' => 'global_cta_text',
                'type' => 'textarea',
                'instructions' => self::translate_string('Text for fallback CTA section.'),
                'rows' => 4,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'global_cta_link',
                'label' => self::translate_string('CTA link'),
                'name' => 'global_cta_link',
                'type' => 'link',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'fallbacks_tab',
                'label' => self::translate_string('Fallbacks'),
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'fallback_home_title',
                'label' => self::translate_string('Home fallback title'),
                'name' => 'fallback_home_title',
                'type' => 'text',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'fallback_home_content',
                'label' => self::translate_string('Home fallback content'),
                'name' => 'fallback_home_content',
                'type' => 'textarea',
                'rows' => 6,
                'new_lines' => 'wpautop',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'fallback_404_title',
                'label' => self::translate_string('404 fallback title'),
                'name' => 'fallback_404_title',
                'type' => 'text',
            ],
            [
                'key' => self::FIELD_KEY_PREFIX . 'fallback_404_message',
                'label' => self::translate_string('404 fallback message'),
                'name' => 'fallback_404_message',
                'type' => 'textarea',
                'rows' => 4,
                'new_lines' => 'wpautop',
            ],
        ];

        acf_add_local_field_group([
            'key' => 'group_wco_theme_settings',
            'title' => self::translate_string('Theme settings'),
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'wco-theme-settings',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'active' => true,
            'show_in_rest' => 1,
        ]);
    }

    public static function render_custom_head_code(): void
    {
        self::render_option_code('custom_head_code');
        self::render_option_code('tracking_head_scripts');

        $gtm = self::field_from_option('google_tag_manager_id');
        if (is_string($gtm) && trim($gtm) !== '') {
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm}');</script>\n";
        }

        $ga4 = self::field_from_option('google_analytics_id');
        if (is_string($ga4) && trim($ga4) !== '') {
            echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$ga4}\"></script>\n";
            echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$ga4}');</script>\n";
        }
    }

    public static function render_custom_footer_code(): void
    {
        self::render_option_code('tracking_body_scripts');
        self::render_option_code('custom_footer_code');
    }

    public static function translate_field(array $field): array
    {
        if (!self::is_polish_locale()) {
            return $field;
        }

        if (!empty($field['label'])) {
            $field['label'] = self::translate_string((string) $field['label']);
        }

        if (!empty($field['instructions'])) {
            $field['instructions'] = self::translate_string((string) $field['instructions']);
        }

        if (!empty($field['message'])) {
            $field['message'] = self::translate_string((string) $field['message']);
        }

        if (!empty($field['button_label'])) {
            $field['button_label'] = self::translate_string((string) $field['button_label']);
        }

        if (!empty($field['choices']) && is_array($field['choices'])) {
            $translatedChoices = [];
            foreach ($field['choices'] as $value => $label) {
                $translatedChoices[$value] = self::translate_string((string) $label);
            }
            $field['choices'] = $translatedChoices;
        }

        return $field;
    }

    public static function translate_field_group(array $group): array
    {
        if (!self::is_polish_locale()) {
            return $group;
        }

        if (!empty($group['title'])) {
            $group['title'] = self::translate_string((string) $group['title']);
        }

        return $group;
    }

    private static function render_option_code(string $fieldName): void
    {
        $code = self::field_from_option($fieldName);
        if (!is_string($code) || trim($code) === '') {
            return;
        }

        echo $code . "\n";
    }

    private static function field_from_option(string $fieldName)
    {
        if (!function_exists('get_field')) {
            return null;
        }

        return get_field($fieldName, 'option');
    }

    private static function is_polish_locale(): bool
    {
        $locale = get_locale();
        return str_starts_with(strtolower($locale), 'pl');
    }

    private static function translate_string(string $value): string
    {
        if (!self::is_polish_locale()) {
            return $value;
        }

        $translations = [
            'Theme settings' => 'Ustawienia motywu',
            'Custom code' => 'Własny kod',
            'Custom code in <head>' => 'Własny kod w <head>',
            'Custom code before </body>' => 'Własny kod przed </body>',
            'Code added before the closing </head> tag.' => 'Kod dodawany przed zamknięciem znacznika </head>.',
            'Code added before the closing </body> tag.' => 'Kod dodawany przed zamknięciem znacznika </body>.',
            'Title' => 'Tytuł',
            'Content' => 'Treść',
            'Eyebrow' => 'Nadtytuł',
            'Image' => 'Obraz',
            'Image position' => 'Pozycja obrazka',
            'Link' => 'Link',
            'Section settings' => 'Ustawienia sekcji',
            'Section background' => 'Tło sekcji',
            'Adds the section background helper class.' => 'Dodaje pomocniczą klasę tła sekcji.',
            'Use .section-bg on this block' => 'Użyj klasy .section-bg dla tego bloku',
            'Gap top' => 'Odstęp zewnętrzny u góry',
            'Gap bottom' => 'Odstęp zewnętrzny na dole',
            'Space top' => 'Padding u góry',
            'Space bottom' => 'Padding na dole',
            'None' => 'Brak',
            'Right' => 'Po prawej',
            'Left' => 'Po lewej',
            'Intro' => 'Wstęp',
            'FAQ items' => 'Pytania FAQ',
            'Add item' => 'Dodaj element',
            'Question' => 'Pytanie',
            'Answer' => 'Odpowiedź',
            'Services' => 'Usługi',
            'Add service' => 'Dodaj usługę',
            'Icon' => 'Ikona',
            'Testimonials' => 'Opinie',
            'Add testimonial' => 'Dodaj opinię',
            'Quote' => 'Cytat',
            'Author name' => 'Imię autora',
            'Author role' => 'Rola autora',
            'Latest Posts Block' => 'Blok Najnowsze wpisy',
            'Posts per page' => 'Liczba wpisów na stronę',
            'Button label' => 'Etykieta przycisku',
            'Loading label' => 'Etykieta ładowania',
            'Read more label' => 'Etykieta czytaj więcej',
            'No image label' => 'Etykieta braku obrazka',
            'Empty label' => 'Etykieta pustej listy',
            'Load more posts' => 'Załaduj więcej wpisów',
            'Loading...' => 'Ładowanie...',
            'Read more' => 'Czytaj więcej',
            'No image' => 'Brak obrazka',
            'No posts found.' => 'Brak wpisów.',
            'Hero Banner Block' => 'Blok Hero Banner',
            'Text image Block' => 'Blok Tekst i obraz',
            'FAQ Accordion Block' => 'Blok FAQ Accordion',
            'Services Block' => 'Blok Usługi',
            'Testimonials Block' => 'Blok Testimonials',
            'Testimonials Slider Block' => 'Blok Slider opinii',
            'Tracking' => 'Śledzenie',
            'Google Tag Manager ID' => 'ID Google Tag Manager',
            'Google Analytics ID' => 'ID Google Analytics',
            'Paste GTM ID for automatic injection e.g. GTM-XXXXXX' => 'Wklej GTM ID do automatycznego wstrzykiwania, np. GTM-XXXXXX',
            'Paste GA4 measurement ID, e.g. G-XXXXXX' => 'Wklej ID pomiarowe GA4, np. G-XXXXXX',
            'Additional scripts in <head>' => 'Dodatkowe skrypty w <head>',
            'Additional scripts before </body>' => 'Dodatkowe skrypty przed </body>',
            'Additional tracking scripts added in <head>.' => 'Dodatkowe skrypty śledzące dodawane w <head>.',
            'Additional tracking scripts added before </body>.' => 'Dodatkowe skrypty śledzące dodawane przed </body>.',
            'Social & Meta' => 'Social i metadane',
            'Facebook URL' => 'Adres Facebook',
            'Instagram URL' => 'Adres Instagram',
            'LinkedIn URL' => 'Adres LinkedIn',
            'X / Twitter URL' => 'Adres X / Twitter',
            'Default meta title' => 'Domyślny tytuł meta',
            'Default meta description' => 'Domyślny opis meta',
            'Banners and CTA' => 'Banery i CTA',
            'Global banner' => 'Baner globalny',
            'Use global banner' => 'Włącz baner globalny',
            'Banner code / HTML' => 'Kod / HTML banera',
            'Shown at the top of every page.' => 'Pokazuje się na górze każdej strony.',
            'Global CTA' => 'Globalne CTA',
            'Enable global CTA section' => 'Włącz sekcję globalnego CTA',
            'CTA title' => 'Tytuł CTA',
            'CTA text' => 'Treść CTA',
            'Title for fallback CTA section.' => 'Tytuł sekcji CTA w fallbacku.',
            'Text for fallback CTA section.' => 'Treść sekcji CTA w fallbackie.',
            'CTA link' => 'Link CTA',
            'Fallbacks' => 'Zastępstwa',
            'Home fallback title' => 'Tytuł fallback strony głównej',
            'Home fallback content' => 'Treść fallback strony głównej',
            '404 fallback title' => 'Tytuł 404',
            '404 fallback message' => 'Treść 404',
        ];

        return $translations[$value] ?? $value;
    }
}
