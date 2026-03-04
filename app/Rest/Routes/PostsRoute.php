<?php

namespace WCO\Starter\Rest\Routes;

use WCO\Starter\Content\PostCard;
use WCO\Starter\Rest\BaseRoute;
use WCO\Starter\Rest\Contracts\RouteInterface;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class PostsRoute extends BaseRoute implements RouteInterface
{
    public static function register(): void
    {
        self::register_route('/posts', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'handle'],
            'args' => [
                'page' => [
                    'sanitize_callback' => 'absint',
                    'default' => 1,
                ],
                'per_page' => [
                    'sanitize_callback' => 'absint',
                    'default' => 6,
                ],
                'post_type' => [
                    'sanitize_callback' => 'sanitize_key',
                    'default' => 'post',
                ],
                'exclude_ids' => [
                    'sanitize_callback' => [self::class, 'sanitize_ids'],
                    'default' => [],
                ],
                'read_more_label' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => 'Read more',
                ],
                'no_image_label' => [
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => 'No image',
                ],
            ],
        ]);
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(24, (int) $request->get_param('per_page')));
        $post_type = sanitize_key((string) $request->get_param('post_type')) ?: 'post';
        $exclude_ids = self::sanitize_ids($request->get_param('exclude_ids'));
        $read_more_label = sanitize_text_field((string) $request->get_param('read_more_label')) ?: 'Read more';
        $no_image_label = sanitize_text_field((string) $request->get_param('no_image_label')) ?: 'No image';

        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'paged' => $page,
            'posts_per_page' => $per_page,
            'post__not_in' => $exclude_ids,
            'ignore_sticky_posts' => true,
        ]);

        $posts = array_map(
            static fn (\WP_Post $post): array => self::map_post($post, $read_more_label, $no_image_label),
            $query->posts
        );

        return new WP_REST_Response([
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'perPage' => $per_page,
                'totalPosts' => (int) $query->found_posts,
                'totalPages' => (int) $query->max_num_pages,
                'hasMore' => $page < (int) $query->max_num_pages,
            ],
        ], 200);
    }

    public static function sanitize_ids($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $value)));
    }

    private static function map_post(\WP_Post $post, string $read_more_label, string $no_image_label): array
    {
        $mapped_post = PostCard::from_post($post);
        $mapped_post['html'] = PostCard::render($mapped_post, [
            'read_more_label' => $read_more_label,
            'placeholder' => $no_image_label,
        ]);

        return $mapped_post;
    }
}
