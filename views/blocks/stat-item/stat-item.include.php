<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('stat-item', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-value', 'content' => '120+']],
        ['core/paragraph', ['className' => 'is-label', 'content' => 'Zrealizowanych projektów']],
    ],
]);
