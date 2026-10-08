<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('timeline', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 4, ['acf/timeline-item', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/timeline-item'],
]);
