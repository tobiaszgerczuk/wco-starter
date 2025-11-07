<?php

namespace WCO\Starter\Blocks;

use Timber\Timber;

class Registry
{
    private const BLOCKS_DIR = 'blocks';
    private const VIEWS_DIR  = 'views';

    public static function register_blocks(): void
    {
        if (!function_exists('acf_register_block_type')) {
            return;
        }

        // === GUTENBERG EDITOR ASSETS ===
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_editor_assets']);

        $blocks_dir = get_template_directory() . '/' . self::VIEWS_DIR . '/' . self::BLOCKS_DIR;
        if (!is_dir($blocks_dir)) return;

        $directories = glob($blocks_dir . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $slug = basename($dir);

            // Sprawdź, czy istnieje .twig
            $twig_file = $dir . '/' . $slug . '.twig';
            if (!file_exists($twig_file)) continue;

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
    
        $js_url = get_template_directory_uri() . '/public/js/front.js';
        wp_enqueue_script(
            'wco-blocks-editor',
            $js_url,
            ['wp-blocks', 'wp-dom', 'wp-element'],
            filemtime(get_template_directory() . '/public/js/front.js'),
            true
        );
    }

    private static function build_block_args(string $slug, string $dir): array
    {
        $title = ucfirst(str_replace(['-', '_'], ' ', $slug));
        $include_file = $dir . '/' . $slug . '.include.php';
        $has_include = file_exists($include_file);
    
        $args = [
            'name'        => $slug,
            'title'       => $title,
            'description' => __("Block: {$title}", 'wco-starter'),
            'category'    => 'layout',
            'icon'        => 'layout',
            'keywords'    => [$slug],
            'supports'    => ['align' => ['full', 'wide']],
            'mode'        => 'preview',
        ];
    
        if ($has_include) {
            $args['render_template'] = self::VIEWS_DIR . '/' . self::BLOCKS_DIR . '/' . $slug . '/' . $slug . '.include.php';
        } else {
            $args['render_callback'] = [__CLASS__, 'render_simple_block'];
            $args['slug'] = $slug;
        }
    
        // === CSS Z PUBLIC/BLOCKS ===
        $css_path = get_template_directory() . "/public/blocks/{$slug}/{$slug}.css";
        if (file_exists($css_path)) {
            add_action('enqueue_block_editor_assets', function() use ($slug, $css_path) {
                wp_enqueue_style(
                    "acf-block-{$slug}",
                    get_template_directory_uri() . "/public/blocks/{$slug}/{$slug}.css",
                    [],
                    filemtime($css_path)
                );
            });
        }
        
    
        return $args;
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