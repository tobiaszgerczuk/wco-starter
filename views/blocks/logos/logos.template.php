<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 5, ['core/image', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'core/image'],
];
