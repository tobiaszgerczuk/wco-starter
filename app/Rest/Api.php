<?php
namespace WCO\Starter\Rest;

use WP_REST_Request;
use WP_REST_Response;

class Api
{
    /**
     * Rejestracja endpointów REST dopiero PO załadowaniu WooCommerce.
     */
    public static function boot(): void
    {
        // Gwarantuje, że WooCommerce jest już zainicjalizowany
        if (class_exists('WooCommerce')) {
            add_action('rest_api_init', [__CLASS__, 'register_routes']);
            error_log('✅ WooCommerce detected — WCO REST API routes ready to register');
        } else {
            add_action('woocommerce_init', function () {
                add_action('rest_api_init', [__CLASS__, 'register_routes']);
                error_log('🕐 Delayed WCO REST route registration until WooCommerce init');
            });
        }
    }

    public static function register_routes(): void
    {
        // Testowy endpoint (ping)
        register_rest_route('wco-starter/v1', '/ping', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'callback' => fn() => new WP_REST_Response(['ok' => true, 'message' => 'pong'], 200),
        ]);

        // Koszyk
        register_rest_route('wco-starter/v1', '/cart', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'get_cart'],
        ]);

    }



    /**
     * Zwraca zawartość koszyka WooCommerce
     */
    public static function get_cart(\WP_REST_Request $req): \WP_REST_Response
    {
        // 🔸 Upewniamy się, że WooCommerce istnieje
        if (!function_exists('WC') || !WC()) {
            return new WP_REST_Response([
                'items' => [],
                'total' => '0',
                'count' => 0,
                'error' => 'WooCommerce not initialized',
            ], 200);
        }
    
        // 🔸 Ręczna inicjalizacja sesji i koszyka
        if (null === WC()->session) {
            WC()->initialize_session();
        }
    
        if (null === WC()->cart) {
            wc_load_cart();
        }
    
        $cart = WC()->cart;
        if (!$cart) {
            return new WP_REST_Response([
                'items' => [],
                'total' => '0',
                'count' => 0,
                'error' => 'WooCommerce cart could not be loaded',
            ], 200);
        }
    
        $items = [];
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'];
            if (!$product) continue;
    
            $items[] = [
                'key'      => $item['key'],
                'id'       => $product->get_id(),
                'name'     => $product->get_name(),
                'qty'      => $item['quantity'],
                'price'    => wc_price($product->get_price()),
                'subtotal' => wc_price($item['line_subtotal']),
                'thumb'    => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'link'     => get_permalink($product->get_id()),
            ];
        }
    
        return new WP_REST_Response([
            'items' => $items,
            'total' => $cart->get_cart_total(),
            'count' => $cart->get_cart_contents_count(),
        ], 200);
    }    
}
