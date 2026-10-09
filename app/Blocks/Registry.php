<?php

namespace WCO\Starter\Blocks;

use Timber\Timber;
use WCO\Starter\Blocks\SectionSettings;

class Registry
{
    private const BLOCKS_DIR = 'blocks';
    private const VIEWS_DIR  = 'views';
    private const CATEGORY = 'wco-blocks';

    public static function boot(): void
    {
        add_filter('acf/load_field_group', [self::class, 'section_settings_locations']);
        Editor::boot();

        if (function_exists('get_block_categories')) {
            add_filter('block_categories_all', [self::class, 'register_block_category'], 10, 2);
        } else {
            add_filter('block_categories', [self::class, 'register_block_category_legacy'], 10, 2);
        }
    }

    public static function register_blocks(): void
    {
        // === GUTENBERG EDITOR ASSETS ===
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_editor_assets']);

        self::register_json_blocks();
        self::register_container_group_block();
        self::register_two_columns_block();
        self::register_two_columns_column_block();

        if (!function_exists('acf_register_block_type')) {
            return;
        }

        $blocks_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR;
        if (!is_dir($blocks_dir)) {
            return;
        }

        $directories = glob($blocks_dir . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $slug = basename($dir);

            if (in_array($slug, ['container-group', 'two-columns', 'two-columns-column'], true)) {
                continue;
            }

            // Blocks with a "name" in block.json are registered by register_json_blocks().
            if (self::is_json_block($dir)) {
                continue;
            }

            // Sprawdź, czy istnieje .twig
            $twig_file = $dir . '/' . $slug . '.twig';
            if (!file_exists($twig_file)) {
                continue;
            }

            $args = self::build_block_args($slug, $dir);
            acf_register_block_type($args);
        }
    }

    /**
     * Registers ACF blocks described by block.json ("name" + "acf" keys). Content lives in
     * InnerBlocks (edited in the preview); ACF fields only hold settings.
     */
    private static function register_json_blocks(): void
    {
        $blocks_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR;

        foreach (glob($blocks_dir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!self::is_json_block($dir)) {
                continue;
            }

            $slug = basename($dir);
            $args = [];
            if (self::is_polish_locale()) {
                // block.json is read directly by WordPress, so hand over the translated labels.
                $metadata = self::get_block_metadata($dir);
                foreach (['title', 'description'] as $key) {
                    if (!empty($metadata[$key])) {
                        $args[$key] = $metadata[$key];
                    }
                }
            }
            $style = self::register_block_style_handle($slug);
            if ($style !== '') {
                $args['style'] = $style;
                $args['editor_style'] = $style;
            }

            register_block_type($dir, $args);
        }
    }

    /**
     * The shared "Section settings" group is attached to every block whose block.json has
     * "wco": { "sectionSettings": true }, so new blocks never need their own copy of the fields.
     */
    public static function section_settings_locations(array $group): array
    {
        if (($group['key'] ?? '') !== 'group_section_settings') {
            return $group;
        }

        $location = [];
        $blocks_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR;

        foreach (glob($blocks_dir . '/*/block.json') ?: [] as $file) {
            $metadata = self::get_block_metadata(dirname($file));
            if (!empty($metadata['wco']['sectionSettings']) && !empty($metadata['name'])) {
                $location[] = [['param' => 'block', 'operator' => '==', 'value' => $metadata['name']]];
            }
        }

        $group['location'] = $location;

        return $group;
    }

    private static function is_json_block(string $dir): bool
    {
        $metadata = self::get_block_metadata($dir);

        return !empty($metadata['name']) && isset($metadata['acf']);
    }

    /**
     * Ładuj style i JS TYLKO w edytorze Gutenberg
     */
    public static function enqueue_editor_assets(): void
    {
        $base_css = get_template_directory() . '/public/blocks/blocks-base.css';
        if (file_exists($base_css)) {
            wp_enqueue_style(
                'wco-blocks-editor-base',
                get_template_directory_uri() . '/public/blocks/blocks-base.css',
                [],
                filemtime($base_css)
            );
        }
    
        $editor_js_path = get_template_directory() . '/public/js/editor_blocks.js';
        $js_url = get_template_directory_uri() . '/public/js/editor_blocks.js';
        wp_enqueue_script(
            'wco-blocks-editor',
            $js_url,
            ['wp-blocks', 'wp-dom', 'wp-element', 'wp-block-editor', 'wp-components'],
            file_exists($editor_js_path) ? filemtime($editor_js_path) : null,
            true
        );
    }

    public static function register_block_category(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? null) === self::CATEGORY) {
                return $categories;
            }
        }

        $categories[] = [
            'slug'  => self::CATEGORY,
            'title' => __('WCO Blocks', 'wco-starter'),
            'icon'  => null,
        ];

        return $categories;
    }

    public static function register_block_category_legacy(array $categories): array
    {
        return self::register_block_category($categories);
    }

    private static function build_block_args(string $slug, string $dir): array
    {
        $title = ucfirst(str_replace(['-', '_'], ' ', $slug));
        $include_file = $dir . '/' . $slug . '.include.php';
        $has_include = file_exists($include_file);
        $metadata = self::get_block_metadata($dir);
    
        $args = [
            'name'        => $slug,
            'title'       => $metadata['title'] ?? $title,
            'description' => $metadata['description'] ?? __("Block: {$title}", 'wco-starter'),
            'category'    => $metadata['category'] ?? self::CATEGORY,
            'icon'        => $metadata['icon'] ?? 'layout',
            'keywords'    => $metadata['keywords'] ?? [$slug],
            'supports'    => isset($metadata['supports']) && is_array($metadata['supports']) ? $metadata['supports'] : ['align' => ['full', 'wide']],
            'mode'        => is_string($metadata['mode'] ?? null) ? $metadata['mode'] : 'preview',
        ];

        if (isset($metadata['supports']) && is_array($metadata['supports']) && !isset($metadata['supports']['align'])) {
            $args['supports']['align'] = ['full', 'wide'];
        }

        if ($has_include) {
            $args['render_template'] = self::VIEWS_DIR . '/' . self::BLOCKS_DIR . '/' . $slug . '/' . $slug . '.include.php';
        } else {
            $args['render_callback'] = [__CLASS__, 'render_simple_block'];
            $args['slug'] = $slug;
        }
    
        $css_path = get_template_directory() . "/public/blocks/{$slug}/{$slug}.css";
        if (file_exists($css_path)) {
            $args['enqueue_assets'] = function () use ($slug, $css_path) {
                wp_enqueue_style(
                    "acf-block-{$slug}",
                    get_template_directory_uri() . "/public/blocks/{$slug}/{$slug}.css",
                    [],
                    filemtime($css_path)
                );
            };
        }

        return $args;
    }

    private static function register_container_group_block(): void
    {
        self::register_native_layout_block(
            'container-group',
            [
                'containerWidth' => [
                    'type' => 'string',
                    'default' => 'default',
                ],
            ],
            [__CLASS__, 'render_container_group_callback']
        );
    }

    private static function register_two_columns_block(): void
    {
        self::register_native_layout_block(
            'two-columns',
            [
                'containerWidth' => [
                    'type' => 'string',
                    'default' => 'default',
                ],
                'columnsRatio' => [
                    'type' => 'string',
                    'default' => '50-50',
                ],
            ],
            [__CLASS__, 'render_two_columns_callback']
        );
    }

    private static function register_two_columns_column_block(): void
    {
        self::register_native_layout_block(
            'two-columns-column',
            [
                'columnPosition' => [
                    'type' => 'string',
                    'default' => 'left',
                ],
            ],
            [__CLASS__, 'render_two_columns_column_callback']
        );
    }

    private static function register_native_layout_block(string $slug, array $default_attributes, callable $render_callback): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        $name = 'acf/' . $slug;

        if (class_exists('\\WP_Block_Type_Registry') && \WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            return;
        }

        $dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR . '/' . $slug;
        if (!is_dir($dir)) {
            return;
        }

        $metadata = self::get_block_metadata($dir);
        $supports = $metadata['supports'] ?? ['align' => ['full', 'wide']];

        if (!is_array($supports['align'] ?? null)) {
            $supports['align'] = ['full', 'wide'];
        }

        $args = [
            'title' => $metadata['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'description' => $metadata['description'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'category' => $metadata['category'] ?? self::CATEGORY,
            'icon' => $metadata['icon'] ?? 'layout',
            'api_version' => $metadata['apiVersion'] ?? 2,
            'supports' => $supports,
            'attributes' => array_replace_recursive(
                $default_attributes,
                is_array($metadata['attributes'] ?? null) ? $metadata['attributes'] : []
            ),
            'render_callback' => $render_callback,
        ];

        $style = self::register_block_style_handle($slug);
        if ($style !== '') {
            $args['style'] = $style;
            $args['editor_style'] = $style;
        }

        register_block_type($name, $args);
    }

    public static function render_container_group_callback($attributes = [], string $content = '', $block = null): string
    {
        ob_start();
        self::render_container_group($attributes, $content, $block);
        return (string) ob_get_clean();
    }

    private static function register_block_style_handle(string $slug): string
    {
        $handle = 'wco-' . $slug;
        $css_path = get_template_directory() . '/public/blocks/' . $slug . '/' . $slug . '.css';

        if (!file_exists($css_path)) {
            return '';
        }

        // Block styles are also loaded in the editor, i.e. on a whole admin screen. The front-end
        // stylesheet restyles <body>, headings and links, so it must not be a dependency there:
        // theme typography reaches the editor canvas through add_editor_style() instead.
        $dependencies = is_admin() ? [] : ['wco-starter-style'];

        if (!wp_style_is($handle, 'registered')) {
            wp_register_style(
                $handle,
                get_template_directory_uri() . '/public/blocks/' . $slug . '/' . $slug . '.css',
                $dependencies,
                filemtime($css_path)
            );
        }

        return $handle;
    }

    private static function get_block_metadata(string $dir): array
    {
        $metadataPath = $dir . '/block.json';
        if (!file_exists($metadataPath)) {
            return [];
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);

        if (!is_array($metadata)) {
            return [];
        }

        if (!self::is_polish_locale()) {
            return $metadata;
        }

        if (!empty($metadata['title']) && is_string($metadata['title'])) {
            $metadata['title'] = self::translate_block_string($metadata['title']);
        }

        if (!empty($metadata['description']) && is_string($metadata['description'])) {
            $metadata['description'] = self::translate_block_string($metadata['description']);
        }

        if (!empty($metadata['keywords']) && is_array($metadata['keywords'])) {
            $metadata['keywords'] = array_map(
                static fn($keyword) => is_string($keyword) ? self::translate_block_string($keyword) : $keyword,
                $metadata['keywords']
            );
        }

        return $metadata;
    }

    private static function is_polish_locale(): bool
    {
        return str_starts_with(strtolower(get_locale()), 'pl');
    }

    private static function translate_block_string(string $value): string
    {
        $blockNames = [
            'Container Group' => 'Grupa kontenera',
            'FAQ Accordion' => 'FAQ akordeon',
            'Hero Banner' => 'Baner hero',
            'Latest Posts' => 'Najnowsze wpisy',
            'Services' => 'Usługi',
            'Spacer' => 'Odstęp',
            'Testimonials Slider' => 'Slider opinii',
            'Text image' => 'Tekst i obraz',
            'Two Columns' => 'Dwie kolumny',
            'Two Columns Column' => 'Kolumna dwóch kolumn',
            'Testowy' => 'Testowy',
            'Contact' => 'Kontakt',
            'Call to Action' => 'Wezwanie do działania',
            'FAQ Item' => 'Pytanie FAQ',
            'FAQ' => 'FAQ',
            'Feature Item' => 'Kafelek',
            'Features' => 'Kafelki',
            'Gallery' => 'Galeria',
            'Hero' => 'Hero',
            'Logos' => 'Logotypy',
            'Map' => 'Mapa',
            'Pricing Plan' => 'Pakiet cennika',
            'Pricing' => 'Cennik',
            'Section Heading' => 'Nagłówek sekcji',
            'Separator' => 'Linia',
            'Stat Item' => 'Liczba',
            'Stats' => 'Liczby',
            'Team Member' => 'Członek zespołu',
            'Team' => 'Zespół',
            'Testimonial Item' => 'Opinia',
            'Testimonials' => 'Opinie',
            'Text and Image' => 'Tekst i obraz',
            'Timeline Step' => 'Etap osi czasu',
            'Timeline' => 'Oś czasu',
            'Video' => 'Wideo',
        ];

        if (isset($blockNames[$value])) {
            return $blockNames[$value];
        }

        if (preg_match('/^Block: (.+)$/', $value, $matches) === 1) {
            $name = $matches[1];
            return 'Blok: ' . ($blockNames[$name] ?? $name);
        }

        $translations = [
            'Wrapper block with configurable container width.' => 'Blok opakowujący z wyborem szerokości kontenera.',
            'Accordion FAQ block.' => 'Blok FAQ w formie akordeonu.',
            'Latest blog posts block with REST pagination.' => 'Blok najnowszych wpisów blogowych z paginacją REST.',
            'Services cards block.' => 'Blok kart usług.',
            'Spacing separator block with independent mobile and desktop height.' => 'Blok separatora odstępu z niezależną wysokością dla mobile i desktop.',
            'Testimonials slider block.' => 'Blok slidera opinii.',
            'Two-column layout block with container width and ratio controls.' => 'Blok układu dwóch kolumn z wyborem szerokości kontenera i proporcji.',
            'Inner column for the Two Columns layout block.' => 'Wewnętrzna kolumna dla bloku układu dwóch kolumn.',
            'Contact details next to a form (Contact Form 7 or another form shortcode).' => 'Dane kontaktowe obok formularza (Contact Form 7 lub inny shortcode).',
            'Heading, text and buttons. Edit directly in the preview.' => 'Nagłówek, tekst i przyciski. Edytujesz bezpośrednio w podglądzie.',
            'Single question and answer. The first heading is the question.' => 'Jedno pytanie i odpowiedź. Pierwszy nagłówek to pytanie.',
            'Accordion of questions. Edit questions and answers in the preview.' => 'Akordeon z pytaniami. Pytania i odpowiedzi edytujesz w podglądzie.',
            'Single feature card.' => 'Pojedynczy kafelek.',
            'Grid of feature cards. Add, reorder and edit cards in the preview.' => 'Siatka kafelków. Dodajesz, przestawiasz i edytujesz je w podglądzie.',
            'Image grid. Add images in the preview.' => 'Siatka zdjęć. Zdjęcia dodajesz w podglądzie.',
            'Hero with text and image, optional background image. Edit text and image in the preview.' => 'Hero z tekstem i zdjęciem, opcjonalnie z obrazem w tle. Tekst i zdjęcie edytujesz w podglądzie.',
            'Newest posts in cards. Edit the heading in the preview; options in the sidebar.' => 'Najnowsze wpisy w kartach. Nagłówek edytujesz w podglądzie, opcje w panelu bocznym.',
            'Client logos. Add images and links in the preview.' => 'Logotypy klientów. Obrazy i linki dodajesz w podglądzie.',
            'Embedded map that loads after a click (privacy friendly).' => 'Mapa wczytywana po kliknięciu (przyjazna prywatności).',
            'Single pricing plan.' => 'Pojedynczy pakiet cennika.',
            'Pricing plans. Add, reorder and edit plans in the preview.' => 'Pakiety cennika. Dodajesz, przestawiasz i edytujesz je w podglądzie.',
            'Eyebrow, heading and lead text. Edit directly in the preview.' => 'Nadtytuł, nagłówek i lead. Edytujesz bezpośrednio w podglądzie.',
            'Horizontal line with style, width and colour options.' => 'Pozioma linia z opcjami stylu, szerokości i koloru.',
            'Single number with a label.' => 'Jedna liczba z podpisem.',
            'Row of numbers with labels. Edit numbers and labels in the preview.' => 'Rząd liczb z podpisami. Liczby i podpisy edytujesz w podglądzie.',
            'Single team member.' => 'Jedna osoba z zespołu.',
            'Team members. Add, reorder and edit people in the preview.' => 'Zespół. Osoby dodajesz, przestawiasz i edytujesz w podglądzie.',
            'Single customer quote.' => 'Pojedyncza opinia klienta.',
            'Customer quotes. Add, reorder and edit quotes in the preview.' => 'Opinie klientów. Dodajesz, przestawiasz i edytujesz je w podglądzie.',
            'Two columns: text and image. Edit both directly in the preview.' => 'Dwie kolumny: tekst i zdjęcie. Oba edytujesz bezpośrednio w podglądzie.',
            'Single milestone.' => 'Pojedynczy etap.',
            'Milestones on a vertical line. Add, reorder and edit steps in the preview.' => 'Etapy na pionowej osi. Dodajesz, przestawiasz i edytujesz je w podglądzie.',
            'YouTube, Vimeo or a video file. The player loads after a click (privacy friendly).' => 'YouTube, Vimeo lub plik wideo. Odtwarzacz wczytuje się po kliknięciu (przyjazny prywatności).',
            'container' => 'kontener',
            'group' => 'grupa',
            'wrapper' => 'wrapper',
            'section' => 'sekcja',
            'faq' => 'faq',
            'accordion' => 'akordeon',
            'questions' => 'pytania',
            'posts' => 'wpisy',
            'blog' => 'blog',
            'news' => 'aktualności',
            'services' => 'usługi',
            'spacer' => 'separator',
            'space' => 'odstęp',
            'separator' => 'separator',
            'cards' => 'karty',
            'offer' => 'oferta',
            'testimonials' => 'opinie',
            'slider' => 'slider',
            'reviews' => 'recenzje',
            'columns' => 'kolumny',
            'layout' => 'układ',
            'split' => 'podział',
            'content' => 'treść',
        ];

        return $translations[$value] ?? $value;
    }

    public static function render_simple_block($block, $content = '', $is_preview = false, $post_id = 0): void
    {
        $slug = $block['slug'] ?? str_replace('acf/', '', $block['name']);
        $context = [
            'fields'     => get_fields() ?: [],
            'is_preview' => $is_preview,
            'block'      => $block,
        ];

        Timber::render("blocks/{$slug}/{$slug}.twig", $context);
    }

    public static function render_container_group($attributes = [], string $content = '', $block = null): void
    {
        $context = self::build_layout_block_context($attributes, $block, $content, 'block-container-group');
        Timber::render('blocks/container-group/container-group.twig', $context);
    }

    public static function render_two_columns_callback($attributes = [], string $content = '', $block = null): string
    {
        ob_start();
        self::render_two_columns($attributes, $content, $block);
        return (string) ob_get_clean();
    }

    public static function render_two_columns($attributes = [], string $content = '', $block = null): void
    {
        $context = self::build_layout_block_context($attributes, $block, $content, 'block-two-columns');
        $allowed_ratios = ['50-50', '60-40', '40-60', '70-30', '30-70'];
        $ratio = is_string($attributes['columnsRatio'] ?? null) ? $attributes['columnsRatio'] : '50-50';
        $context['ratio_class'] = 'block-two-columns__grid--ratio-' . (in_array($ratio, $allowed_ratios, true) ? $ratio : '50-50');

        Timber::render('blocks/two-columns/two-columns.twig', $context);
    }

    public static function render_two_columns_column_callback($attributes = [], string $content = '', $block = null): string
    {
        ob_start();
        self::render_two_columns_column($attributes, $content, $block);
        return (string) ob_get_clean();
    }

    public static function render_two_columns_column($attributes = [], string $content = '', $block = null): void
    {
        $position = is_string($attributes['columnPosition'] ?? null) ? $attributes['columnPosition'] : 'left';
        if (!in_array($position, ['left', 'right'], true)) {
            $position = 'left';
        }

        Timber::render('blocks/two-columns-column/two-columns-column.twig', [
            'content' => $content,
            'column_class' => 'block-two-columns__column--' . $position,
        ]);
    }

    private static function build_layout_block_context($attributes, $block, string $content, string $base_class): array
    {
        $context = Timber::context();
        $fields = [];
        $block_attrs = [];

        if (function_exists('get_fields') && is_array($block) && !empty($block['id'])) {
            $fields = get_fields($block['id']) ?: [];
        }

        if (is_array($block)) {
            $block_attrs = $block['attrs']['data'] ?? [];
        } elseif (is_object($block) && isset($block->attributes)) {
            $block_attrs = $block->attributes;
        }

        if (is_array($block_attrs) && !empty($block_attrs)) {
            $fields = array_replace_recursive($fields, $block_attrs);
        }

        if (!is_array($fields)) {
            $fields = [];
        }

        if (!empty($attributes['containerWidth'])) {
            $fields['container_width'] = $attributes['containerWidth'];
        }

        $context['fields'] = $fields;
        $context['block'] = is_array($block) ? $block : [];
        $context['is_preview'] = is_admin();
        $context['post_id'] = 0;
        $context['content'] = $content;
        $align = null;
        if (is_array($block) && !empty($block['align'])) {
            $align = $block['align'];
        } elseif (is_object($block) && property_exists($block, 'attributes') && is_array($block->attributes) && !empty($block->attributes['align'])) {
            $align = $block->attributes['align'];
        }
        $context['section_classes'] = SectionSettings::build_classes(
            $fields,
            [$base_class, $align ? 'align' . $align : '']
        );
        $context['section_id'] = SectionSettings::section_id($fields);
        $context['section_style'] = SectionSettings::inline_style($fields);
        $context['container_class'] = SectionSettings::container_class($fields);
        return $context;
    }
}
