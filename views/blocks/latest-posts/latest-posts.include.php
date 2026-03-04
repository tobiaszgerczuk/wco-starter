<?php

use Timber\Timber;
use WCO\Starter\Blocks\SectionSettings;
use WCO\Starter\Content\PostCard;

$fields = get_fields() ?: [];
$per_page = max(1, (int) ($fields['latest_posts_posts_per_page'] ?? 3));

$query = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'ignore_sticky_posts' => true,
]);

$posts = array_map(static fn (WP_Post $post): array => PostCard::from_post($post), $query->posts);

$context = Timber::context();
$context['fields'] = $fields;
$context['block'] = $block ?? [];
$context['is_preview'] = $is_preview ?? false;
$context['post_id'] = $post_id ?? 0;
$context['section_classes'] = SectionSettings::build_classes(
    $fields,
    ['block-latest-posts', !empty($context['block']['align']) ? 'align' . $context['block']['align'] : '']
);
$context['posts'] = $posts;
$context['posts_query'] = [
    'page' => 1,
    'perPage' => $per_page,
    'totalPages' => (int) $query->max_num_pages,
    'hasMore' => 1 < (int) $query->max_num_pages,
];
$context['latest_posts_rest_url'] = rest_url('wco-starter/v1/posts');

Timber::render('blocks/latest-posts/latest-posts.twig', $context);
