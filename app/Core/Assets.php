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
            [],
            self::get_version('js/front.js'),
            true
        );
        wp_script_add_data('wco-starter-front', 'strategy', 'defer');

        // Admin JS
        wp_register_script(
            'wco-starter-admin',
            self::$base_url . '/js/admin.js',
            [],
            self::get_version('js/admin.js'),
            true
        );

        // Admin CSS
        wp_register_style(
            'wco-starter-admin-style',
            self::$base_url . '/admin_style.css',
            [],
            self::get_version('admin_style.css')
        );
    }

    /**
     * Front: enqueue + localize
     */
    public static function enqueue_front(): void {
        self::preload();
        wp_enqueue_style('wco-starter-style');
        wp_enqueue_script('wco-starter-front');

        $wooActive = class_exists('WooCommerce');

        wp_localize_script('wco-starter-front', 'WCO', [
            'restUrl' => esc_url_raw(rest_url('wco-starter/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'wooActive' => $wooActive,
            'cartUrl' => $wooActive && function_exists('wc_get_cart_url') ? esc_url_raw(wc_get_cart_url()) : null,
        ]);
    }

    public static function preload(): void
    {
        self::init_paths();

        $stylePath = self::$base_path . '/style.css';
        $scriptPath = self::$base_path . '/js/front.js';
        if (is_admin() || !is_readable($stylePath) || !is_readable($scriptPath)) {
            return;
        }

        $ver = self::get_version('style.css') ?: time();
        $scriptVer = self::get_version('js/front.js') ?: time();
        $styleUrl = esc_url(self::$base_url . '/style.css');
        $scriptUrl = esc_url(self::$base_url . '/js/front.js');

        printf(
            "<link rel=\"preload\" as=\"style\" href=\"%s?ver=%s\">\n",
            $styleUrl,
            rawurlencode((string) $ver)
        );
        printf(
            "<link rel=\"preload\" as=\"script\" href=\"%s?ver=%s\">\n",
            $scriptUrl,
            rawurlencode((string) $scriptVer)
        );
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
