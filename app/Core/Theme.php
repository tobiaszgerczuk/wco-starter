<?php

namespace WCO\Starter\Core;

use Timber\Timber;
use WCO\Starter\Core\Assets;
use WCO\Starter\Core\Templating;
use WCO\Starter\Blocks\Registry as BlocksRegistry;
use WCO\Starter\Rest\Api as RestApi;

class Theme
{
    public static function init(): void
    {
        // Timber config
        Timber::$dirname = ['views'];
        Timber::$autoescape = false;

        add_action('after_setup_theme', [self::class, 'supports']);

        // === REJESTRACJA I ENQUEUE ASSETÓW ===
        add_action('init', [Assets::class, 'register']);
        add_action('wp_enqueue_scripts', [Assets::class, 'enqueue_front'], 20);
        add_action('admin_enqueue_scripts', [Assets::class, 'enqueue_admin'], 20);

        // Timber/Twig
        add_filter('timber/context', [Templating::class, 'add_to_context']);
        add_filter('timber/twig', [Templating::class, 'extend_twig']);

        // ACF Blocks
        add_action('acf/init', [BlocksRegistry::class, 'register_blocks']);

        // REST API
        RestApi::boot();

        add_action('init', function () {
            register_post_type('inwestycja', [
              'labels' => [
                'name' => 'Inwestycje',
                'singular_name' => 'Inwestycja',
              ],
              'public' => true,
              'menu_icon' => 'dashicons-location-alt',
              'supports' => ['title', 'editor', 'thumbnail'],
              'show_in_rest' => true,
            ]);
          });
          
          
    }

    

    // WCO\Starter\Core\Theme.php
    public static function supports(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

        // === REJESTRACJA MENU ===
        register_nav_menus([
            'main-menu' => __('Main Menu', 'wco-starter'),     // Header
            'footer-menu' => __('Footer Menu', 'wco-starter'),   // Stopka
        ]);

        // WooCommerce support
        add_theme_support('woocommerce');
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');

    }


    
}