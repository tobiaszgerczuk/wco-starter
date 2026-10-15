<?php

return static function (array $fields, string $content): array {
    $items = $fields['testimonials_slider_items'] ?? [];

    // Content saved by the old ACF block keeps the repeater as <name>_<index>_<subfield> rows.
    if (!is_array($items)) {
        $count = (int) $items;
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'quote' => (string) ($fields["testimonials_slider_items_{$i}_quote"] ?? ''),
                'author_name' => (string) ($fields["testimonials_slider_items_{$i}_author_name"] ?? ''),
                'author_role' => (string) ($fields["testimonials_slider_items_{$i}_author_role"] ?? ''),
            ];
        }
    }

    $clean = [];
    foreach ($items as $item) {
        $item = is_array($item) ? $item : [];
        $quote = wp_kses_post((string) ($item['quote'] ?? ''));
        $name = sanitize_text_field((string) ($item['author_name'] ?? ''));
        if (trim(wp_strip_all_tags($quote)) === '' && $name === '') {
            continue;
        }
        $clean[] = ['quote' => $quote, 'author_name' => $name, 'author_role' => sanitize_text_field((string) ($item['author_role'] ?? ''))];
    }

    return [
        'slider_items' => $clean,
        'slider_eyebrow' => esc_html((string) ($fields['testimonials_slider_eyebrow'] ?? '')),
        'slider_title' => esc_html((string) ($fields['testimonials_slider_title'] ?? '')),
        'slider_intro' => wp_kses_post((string) ($fields['testimonials_slider_intro'] ?? '')),
    ];
};
