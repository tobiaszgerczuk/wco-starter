<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template(),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph'],
];
