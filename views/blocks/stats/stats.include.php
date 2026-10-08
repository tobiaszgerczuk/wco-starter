<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('stats', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ['acf/stat-item', []],
        ['acf/stat-item', []],
        ['acf/stat-item', []],
        ['acf/stat-item', []],
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/stat-item'],
]);
