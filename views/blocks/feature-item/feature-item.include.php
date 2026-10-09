<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('feature-item', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/image', ['className' => 'is-icon']],
        ['core/heading', ['level' => 3, 'content' => 'Tytuł kafelka']],
        ['core/paragraph', ['content' => 'Krótki opis kafelka. Kliknij i edytuj.']],
    ],
]);
