<?php

use WCO\Starter\Blocks\BlockContext;
use WCO\Starter\Content\PostCard;

$fields = get_fields() ?: [];
$per_page = max(1, min(12, (int) ($fields['latest_posts_count'] ?? 3)));
$category = (int) ($fields['latest_posts_category'] ?? 0);
$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'ignore_sticky_posts' => true,
];
if ($category > 0) {
    $args['cat'] = $category;
}
$query = new WP_Query($args);
$posts = array_map(static fn (WP_Post $post): array => PostCard::from_post($post), $query->posts);

BlockContext::render('latest-posts', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph'],
    'posts' => $posts,
    'posts_query' => [
        'page' => 1,
        'perPage' => $per_page,
        'category' => $category,
        'totalPages' => (int) $query->max_num_pages,
        'hasMore' => 1 < (int) $query->max_num_pages,
    ],
    'rest_url' => rest_url('wco-starter/v1/posts'),
]);
