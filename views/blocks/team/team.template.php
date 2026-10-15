<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/team-member', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/team-member'],
];
