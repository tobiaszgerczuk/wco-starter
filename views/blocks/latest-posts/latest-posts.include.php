<?php

use WCO\Starter\Blocks\BlockContext;
use WCO\Starter\Content\PostCard;

$fields = get_fields() ?: [];
$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => max(1, min(12, (int) ($fields['latest_posts_count'] ?? 3))),
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
];
if (!empty($fields['latest_posts_category'])) {
    $args['cat'] = (int) $fields['latest_posts_category'];
}
$posts = array_map(static fn (WP_Post $post): array => PostCard::from_post($post), (new WP_Query($args))->posts);

BlockContext::render('latest-posts', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph'],
    'posts' => $posts,
]);
