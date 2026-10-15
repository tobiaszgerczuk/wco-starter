<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-plan', 'content' => 'Pakiet Standard']],
        ['core/paragraph', ['className' => 'is-price', 'content' => '199 zł']],
        ['core/paragraph', ['className' => 'is-period', 'content' => 'miesięcznie']],
        ['core/list', ['className' => 'is-features'], [
            ['core/list-item', ['content' => 'Pierwsza funkcja pakietu']],
            ['core/list-item', ['content' => 'Druga funkcja pakietu']],
            ['core/list-item', ['content' => 'Trzecia funkcja pakietu']],
        ]],
        BlockContext::buttons_template('Wybieram'),
    ],
];
