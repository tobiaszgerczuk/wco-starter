<?php

use WCO\Starter\Blocks\BlockContext;

return [
    'inner_template' => [
        ['core/group', ['className' => 'block-text-image__text'], [
            ['core/paragraph', ['className' => 'is-eyebrow', 'content' => 'Nadtytuł']],
            ['core/heading', ['level' => 2, 'content' => 'Nagłówek sekcji']],
            ['core/paragraph', ['content' => 'Tutaj wpisz treść. Możesz dodać kolejne akapity, listy i przyciski.']],
            BlockContext::buttons_template(),
        ]],
        ['core/image', []],
    ],
];
