<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('hero', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/group', ['className' => 'block-hero__text'], [
            ['core/paragraph', ['className' => 'is-eyebrow', 'content' => 'Nadtytuł']],
            ['core/heading', ['level' => 1, 'content' => 'Nagłówek sekcji hero']],
            ['core/paragraph', ['className' => 'is-lead', 'content' => 'Krótki opis, który możesz edytować bezpośrednio w podglądzie.']],
            BlockContext::buttons_template('Dowiedz się więcej', 'Kontakt'),
        ]],
        ['core/image', []],
    ],
]);
