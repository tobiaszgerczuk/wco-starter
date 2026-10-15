<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/feature-item', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'core/buttons', 'acf/feature-item'],
];
