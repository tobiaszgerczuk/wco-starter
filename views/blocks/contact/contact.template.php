<?php

return [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-eyebrow', 'content' => 'Kontakt']],
        ['core/heading', ['level' => 2, 'content' => 'Napisz do nas']],
        ['core/paragraph', ['content' => 'Odpowiemy najszybciej, jak to możliwe.']],
        ['core/list', ['className' => 'is-contact-list'], [
            ['core/list-item', ['content' => 'ul. Przykładowa 1, 00-001 Warszawa']],
            ['core/list-item', ['content' => '+48 123 456 789']],
            ['core/list-item', ['content' => 'kontakt@example.com']],
        ]],
    ],
];
