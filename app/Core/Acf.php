<?php

namespace WCO\Starter\Core;

class Acf
{
    public static function boot(): void
    {
        add_action('acf/init', [self::class, 'register_options_page']);
        add_action('wp_head', [self::class, 'render_custom_head_code'], 99);
        add_action('wp_footer', [self::class, 'render_custom_footer_code'], 99);
        add_filter('acf/load_field', [self::class, 'translate_field']);
        add_filter('acf/load_field_group', [self::class, 'translate_field_group']);
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

        return array_values(array_unique($paths));
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

        acf_add_local_field_group([
            'key' => 'group_wco_theme_settings',
            'title' => self::translate_string('Theme settings'),
            'fields' => [
                [
                    'key' => 'field_wco_custom_code_tab',
                    'label' => self::translate_string('Custom code'),
                    'name' => '',
                    'type' => 'tab',
                    'placement' => 'top',
                    'endpoint' => 0,
                ],
                [
                    'key' => 'field_wco_custom_head_code',
                    'label' => self::translate_string('Custom code in <head>'),
                    'name' => 'custom_head_code',
                    'type' => 'textarea',
                    'instructions' => self::translate_string('Code added before the closing </head> tag.'),
                    'rows' => 8,
                    'new_lines' => '',
                ],
                [
                    'key' => 'field_wco_custom_footer_code',
                    'label' => self::translate_string('Custom code before </body>'),
                    'name' => 'custom_footer_code',
                    'type' => 'textarea',
                    'instructions' => self::translate_string('Code added before the closing </body> tag.'),
                    'rows' => 8,
                    'new_lines' => '',
                ],
            ],
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
            'show_in_rest' => 0,
        ]);
    }

    public static function render_custom_head_code(): void
    {
        self::render_option_code('custom_head_code');
    }

    public static function render_custom_footer_code(): void
    {
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
        if (!function_exists('get_field')) {
            return;
        }

        $code = get_field($fieldName, 'option');
        if (!is_string($code) || trim($code) === '') {
            return;
        }

        echo $code . "\n";
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
        ];

        return $translations[$value] ?? $value;
    }
}
