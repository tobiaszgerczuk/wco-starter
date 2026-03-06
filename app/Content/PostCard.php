<?php

namespace WCO\Starter\Content;

use Timber\Timber;
use WP_Post;

class PostCard
{
    public static function from_post(WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'excerpt' => get_the_excerpt($post),
            'permalink' => get_permalink($post),
            'date' => get_the_date('', $post),
            'author' => get_the_author_meta('display_name', (int) $post->post_author),
            'image' => get_the_post_thumbnail_url($post, 'large'),
            'image_id' => get_post_thumbnail_id($post),
            'image_alt' => get_post_meta((int) get_post_thumbnail_id($post), '_wp_attachment_image_alt', true),
        ];
    }

    public static function render(array $post, array $options = []): string
    {
        return Timber::compile('modules/post-card.twig', [
            'post' => $post,
            'class' => $options['class'] ?? '',
            'placeholder' => $options['placeholder'] ?? 'No image',
            'read_more_label' => $options['read_more_label'] ?? 'Read more',
        ]);
    }
}
