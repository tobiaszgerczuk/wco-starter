<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('testimonials', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/testimonial-item', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/testimonial-item'],
]);
