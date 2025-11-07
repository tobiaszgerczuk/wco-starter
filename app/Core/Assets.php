<?php

namespace WCO\Starter\Core;

class Assets {
    private static $base_path;
    private static $base_url;

    /**
     * Inicjalizacja ścieżek
     */
    private static function init_paths(): void {
        self::$base_path = get_template_directory() . '/public';
        self::$base_url  = get_template_directory_uri() . '/public';
    }

    /**
     * Rejestracja assetów
     */
    public static function register(): void {
        self::init_paths();

        // Front CSS
        wp_register_style(
            'wco-starter-style',
            self::$base_url . '/style.css',
            [],
            self::get_version('/style.css')
        );

        // Front JS
        wp_register_script(
            'wco-starter-front',
            self::$base_url . '/js/front.js',
            ['jquery'],
            self::get_version('js/front.js'),
            true
        );

        // Admin JS
        wp_register_script(
            'wco-starter-admin',
            self::$base_url . '/js/admin.js',
            ['jquery'],
            self::get_version('js/admin.js'),
            true
        );

        // Admin CSS
        wp_register_style(
            'wco-starter-admin-style',
            self::$base_url . '/css/admin_style.css',
            [],
            self::get_version('css/admin_style.css')
        );
    }

    /**
     * Front: enqueue + localize
     */
    public static function enqueue_front(): void {
        wp_enqueue_style('wco-starter-style');
        wp_enqueue_script('wco-starter-front');

        wp_localize_script('wco-starter-front', 'WCO', [
            'restUrl' => esc_url_raw(rest_url('wco-starter/v1')),
            'nonce'   => wp_create_nonce('wp_rest')
        ]);
    }

    /**
     * Admin: enqueue
     */
    public static function enqueue_admin(): void {
        wp_enqueue_style('wco-starter-admin-style');  // style admina
        wp_enqueue_script('wco-starter-admin');       // JS admina
    }

    /**
     * Cache-busting: filemtime()
     */
    private static function get_version(string $file): ?string {
        $path = self::$base_path . '/' . ltrim($file, '/');
        return file_exists($path) ? filemtime($path) : null;
    }
}