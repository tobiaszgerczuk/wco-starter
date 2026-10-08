<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('team-member', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/image', ['className' => 'is-photo']],
        ['core/heading', ['level' => 3, 'content' => 'Imię Nazwisko']],
        ['core/paragraph', ['className' => 'is-role', 'content' => 'Stanowisko']],
        ['core/paragraph', ['content' => 'Krótki opis osoby.']],
    ],
]);
