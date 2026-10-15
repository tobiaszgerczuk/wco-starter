<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
        ['acf/stat-item', []],
        ['acf/stat-item', []],
        ['acf/stat-item', []],
        ['acf/stat-item', []],
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/stat-item'],
];
