<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('faq-item', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/heading', ['level' => 3, 'content' => 'Pytanie?']],
        ['core/paragraph', ['content' => 'Odpowiedź na pytanie. Kliknij i edytuj.']],
    ],
]);
