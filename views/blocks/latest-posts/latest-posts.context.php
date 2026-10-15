<?php

use WCO\Starter\Content\PostCard;

return static function (array $fields, string $content): array {
    $perPage = max(1, min(12, (int) ($fields['latest_posts_count'] ?? 3)));
    $category = (int) ($fields['latest_posts_category'] ?? 0);
    $args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $perPage,
        'ignore_sticky_posts' => true,
    ];
    if ($category > 0) {
        $args['cat'] = $category;
    }
    $query = new WP_Query($args);

    return [
        'posts' => array_map(static fn (WP_Post $post): array => PostCard::from_post($post), $query->posts),
        'posts_query' => [
            'page' => 1,
            'perPage' => $perPage,
            'category' => $category,
            'totalPages' => (int) $query->max_num_pages,
            'hasMore' => 1 < (int) $query->max_num_pages,
        ],
        'rest_url' => rest_url('wco-starter/v1/posts'),
    ];
};
