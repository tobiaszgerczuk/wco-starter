<?php
namespace WCO\Starter\Rest;

use WP_REST_Request;
use WP_REST_Response;

class Api {
    public static function register_routes(): void
    {
        error_log('✅ WCO\\Starter\\Rest\\Api loaded and routes registered');
        // Testowy endpoint (ping)
        register_rest_route('wco-starter/v1', '/ping', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function(WP_REST_Request $req) {
                return new WP_REST_Response([ 'ok' => true, 'message' => 'pong' ], 200);
            }
        ]);

        // 🔹 Endpoint: inwestycje
        register_rest_route('wco-starter/v1', '/inwestycje', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'get_inwestycje'],
        ]);

        add_action('rest_api_init', function() {
            global $wp_rest_server;
            $routes = array_keys($wp_rest_server->get_routes());
            foreach ($routes as $route) {
                if (strpos($route, 'wco-starter') !== false) {
                    error_log('📡 ROUTE ZAREJESTROWANY: ' . $route);
                }
            }
        }, 20);
    }

    /**
     * Zwraca dane inwestycji (CPT + pola ACF)
     */
    public static function get_inwestycje(WP_REST_Request $req): WP_REST_Response
    {
        error_log('🔥 get_inwestycje() uruchomione');
    
        $posts = get_posts([
            'post_type'      => 'inwestycja',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
    
        $data = array_map(function ($post) {
            $polygon = get_field('polygon_points', $post->ID);
            $points = implode(', ', [
                $polygon['point_top_left'] ?? '',
                $polygon['point_top_right'] ?? '',
                $polygon['point_bottom_right'] ?? '',
                $polygon['point_bottom_left'] ?? '',
            ]);
    
            // 🔹 Treść z edytora Gutenberga (przetworzona przez filtry WP)
            $content = apply_filters('the_content', $post->post_content);
    
            return [
                'id'       => get_field('number', $post->ID),
                'title'    => $post->post_title,
                'status'   => get_field('status', $post->ID),
                'points'   => trim($points, ', '),
                'price'    => get_field('price', $post->ID),
                'size'     => get_field('size', $post->ID),
                'content'  => $content, // ✅ gotowy HTML opis działki
                'desc'     => get_field('short_description', $post->ID),
                'image'    => get_field('map_image', $post->ID)['url'] ?? null,
            ];
        }, $posts);
    
        return new WP_REST_Response($data, 200);
    }
    
}
