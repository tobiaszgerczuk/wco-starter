<?php

namespace WCO\Starter\Rest\Routes;

use WCO\Starter\Rest\BaseRoute;
use WCO\Starter\Rest\Contracts\RouteInterface;
use WP_REST_Request;
use WP_REST_Response;

class CartRoute extends BaseRoute implements RouteInterface
{
    public static function register(): void
    {
        self::register_route('/cart', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'handle'],
        ]);
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WooCommerce') || !function_exists('WC') || !WC()) {
            return new WP_REST_Response([
                'items' => [],
                'total' => '0',
                'count' => 0,
                'error' => 'WooCommerce not initialized',
            ], 200);
        }

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
            $product = $item['data'] ?? null;
            if (!$product) {
                continue;
            }

            $items[] = [
                'key' => $item['key'],
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'qty' => $item['quantity'],
                'price' => wc_price($product->get_price()),
                'subtotal' => wc_price($item['line_subtotal']),
                'thumb' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'link' => get_permalink($product->get_id()),
            ];
        }

        return new WP_REST_Response([
            'items' => $items,
            'total' => $cart->get_cart_total(),
            'count' => $cart->get_cart_contents_count(),
        ], 200);
    }
}
