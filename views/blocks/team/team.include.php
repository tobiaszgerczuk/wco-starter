<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('team', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/team-member', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/team-member'],
]);
