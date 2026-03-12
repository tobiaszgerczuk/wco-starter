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

        self::register_container_group_block();

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

            if ($slug === 'container-group') {
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
        if (!function_exists('register_block_type')) {
            return;
        }

        if (class_exists('\\WP_Block_Type_Registry')) {
            if (\WP_Block_Type_Registry::get_instance()->is_registered('acf/container-group')) {
                return;
            }
        }

        $container_group_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR . '/container-group';
        if (!is_dir($container_group_dir)) {
            return;
        }

        $metadata = self::get_block_metadata($container_group_dir);
        $supports = $metadata['supports'] ?? ['align' => ['full', 'wide']];

        if (!is_array($supports['align'] ?? null)) {
            $supports['align'] = ['full', 'wide'];
        }

        $args = [
            'title' => $metadata['title'] ?? 'Container Group',
            'description' => $metadata['description'] ?? __('Container Group', 'wco-starter'),
            'category' => $metadata['category'] ?? self::CATEGORY,
            'icon' => $metadata['icon'] ?? 'align-wide',
            'api_version' => $metadata['apiVersion'] ?? 2,
            'supports' => $supports,
            'attributes' => array_replace_recursive(
                [
                    'containerWidth' => [
                        'type' => 'string',
                        'default' => 'default',
                    ],
                ],
                is_array($metadata['attributes'] ?? null) ? $metadata['attributes'] : []
            ),
            'render_callback' => [__CLASS__, 'render_container_group_callback'],
        ];

        $groupStyle = self::register_container_group_style();
        if ($groupStyle !== '') {
            $args['style'] = $groupStyle;
            $args['editor_style'] = $groupStyle;
        }

        register_block_type('acf/container-group', $args);
    }

    public static function render_container_group_callback($attributes = [], string $content = '', $block = null): string
    {
        ob_start();
        self::render_container_group($attributes, $content, $block);
        return (string) ob_get_clean();
    }

    private static function register_container_group_style(): string
    {
        $handle = 'wco-container-group';
        $css_path = get_template_directory() . '/public/blocks/container-group/container-group.css';

        if (!file_exists($css_path)) {
            return '';
        }

        if (!wp_style_is($handle, 'registered')) {
            wp_register_style(
                $handle,
                get_template_directory_uri() . '/public/blocks/container-group/container-group.css',
                ['wco-starter-style'],
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

        return is_array($metadata) ? $metadata : [];
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
        $context = Timber::context();
        $fields = [];
        $block_attrs = [];

        if (function_exists('get_fields')) {
            if (is_array($block) && !empty($block['id'])) {
                $fields = get_fields($block['id']) ?: [];
            }
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
            ['block-container-group', $align ? 'align' . $align : '']
        );
        $context['container_class'] = SectionSettings::container_class($fields);

        Timber::render('blocks/container-group/container-group.twig', $context);
    }
}
