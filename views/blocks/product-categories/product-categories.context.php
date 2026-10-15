<?php

use WCO\Starter\Core\Shop;

return static function (array $fields, string $content): array {
    if (!Shop::active()) {
        return ['categories' => []];
    }

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'parent' => 0,
        'number' => max(1, min(12, (int) ($fields['categories_count'] ?? 4))),
        'exclude' => [(int) get_option('default_product_cat')],
        'orderby' => 'count',
        'order' => 'DESC',
    ]);

    $categories = [];
    foreach (is_wp_error($terms) ? [] : $terms as $term) {
        $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
        $categories[] = [
            'name' => $term->name,
            'url' => (string) get_term_link($term),
            'count' => (int) $term->count,
            'image' => $thumbnailId ? wp_get_attachment_image($thumbnailId, 'woocommerce_thumbnail', false, ['loading' => 'lazy']) : '',
        ];
    }

    return ['categories' => $categories];
};
