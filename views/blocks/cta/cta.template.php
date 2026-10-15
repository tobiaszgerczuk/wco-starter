<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        ['core/heading', ['level' => 2, 'content' => 'Gotowy na współpracę?']],
        ['core/paragraph', ['className' => 'is-lead', 'content' => 'Napisz do nas i omówmy Twój projekt.']],
        BlockContext::buttons_template('Skontaktuj się'),
    ],
];
