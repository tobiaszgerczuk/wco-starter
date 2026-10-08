<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('features', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/feature-item', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'core/buttons', 'acf/feature-item'],
]);
