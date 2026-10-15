<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        BlockContext::head_template('Nasze produkty', 'Wybrane produkty z naszej oferty.', 'Sklep'),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'core/buttons'],
];
