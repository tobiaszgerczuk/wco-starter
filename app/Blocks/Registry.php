<?php

namespace WCO\Starter\Blocks;

use Timber\Timber;

class Registry
{
    private const BLOCKS_DIR = 'blocks';
    private const VIEWS_DIR  = 'views';
    private const CATEGORY = 'wco-blocks';
    private static bool $acf_blocks_available = true;
    private static string $acf_notice_message = '';

    public static function boot(): void
    {
        if (function_exists('get_block_categories')) {
            add_filter('block_categories_all', [self::class, 'register_block_category'], 10, 2);
        } else {
            add_filter('block_categories', [self::class, 'register_block_category_legacy'], 10, 2);
        }

        add_action('admin_notices', [self::class, 'render_admin_notice']);
    }

    public static function register_blocks(): void
    {
        if (!function_exists('acf_register_block_type')) {
            self::$acf_blocks_available = false;
            self::$acf_notice_message = self::build_acf_notice_message();
            error_log('WCO Blocks: ' . self::$acf_notice_message);
            return;
        }

        // === GUTENBERG EDITOR ASSETS ===
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_editor_assets']);

        $blocks_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR;
        if (!is_dir($blocks_dir)) {
            error_log("WCO Blocks: directory not found: {$blocks_dir}");
            return;
        }

        $directories = glob($blocks_dir . '/*', GLOB_ONLYDIR);
        error_log('WCO Blocks: scanning ' . count($directories) . ' block directories in ' . $blocks_dir);

        foreach ($directories as $dir) {
            $slug = basename($dir);

            // Sprawdź, czy istnieje .twig
            $twig_file = $dir . '/' . $slug . '.twig';
            if (!file_exists($twig_file)) {
                error_log("WCO Blocks: skipped {$slug} because {$twig_file} does not exist");
                continue;
            }

            $args = self::build_block_args($slug, $dir);
            acf_register_block_type($args);
            error_log("WCO Blocks: registered acf/{$slug}");
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
    
        $js_url = get_template_directory_uri() . '/public/js/front.js';
        wp_enqueue_script(
            'wco-blocks-editor',
            $js_url,
            ['wp-blocks', 'wp-dom', 'wp-element'],
            filemtime(get_template_directory() . '/public/js/front.js'),
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

    public static function render_admin_notice(): void
    {
        if (self::$acf_blocks_available) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $message = self::$acf_notice_message ?: self::build_acf_notice_message();

        echo '<div class="notice notice-warning"><p><strong>WCO Blocks:</strong> ' . esc_html($message) . '</p></div>';
    }

    private static function build_acf_notice_message(): string
    {
        if (!class_exists('ACF')) {
            return 'Custom blocks are disabled because ACF is not active. Install and activate ACF Pro.';
        }

        return 'Custom blocks are disabled because ACF Pro block API is unavailable. Activate ACF Pro instead of the free ACF plugin.';
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
            'supports'    => ['align' => ['full', 'wide']],
            'mode'        => 'preview',
        ];
    
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
}
