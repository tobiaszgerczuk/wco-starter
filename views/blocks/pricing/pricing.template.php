<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
        ['acf/pricing-item', []],
        ['acf/pricing-item', []],
        ['acf/pricing-item', []],
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/pricing-item'],
];
