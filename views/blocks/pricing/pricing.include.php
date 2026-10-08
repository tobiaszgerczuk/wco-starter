<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('pricing', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ['acf/pricing-item', []],
        ['acf/pricing-item', []],
        ['acf/pricing-item', []],
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/pricing-item'],
]);
