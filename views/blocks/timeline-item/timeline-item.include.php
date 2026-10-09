<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('timeline-item', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-date', 'content' => '2024']],
        ['core/heading', ['level' => 3, 'content' => 'Tytuł etapu']],
        ['core/paragraph', ['content' => 'Opis etapu. Kliknij i edytuj.']],
    ],
]);
