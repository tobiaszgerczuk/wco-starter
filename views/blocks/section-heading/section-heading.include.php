<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('section-heading', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-eyebrow', 'content' => 'Nadtytuł']],
        ['core/heading', ['level' => 2, 'content' => 'Nagłówek sekcji']],
        ['core/paragraph', ['className' => 'is-lead', 'content' => 'Krótki opis sekcji.']],
    ],
]);
