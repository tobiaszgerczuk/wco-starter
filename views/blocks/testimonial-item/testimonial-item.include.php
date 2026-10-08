<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('testimonial-item', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        ['core/paragraph', ['className' => 'is-quote', 'content' => '„Świetna współpraca, polecamy każdemu.”']],
        ['core/paragraph', ['className' => 'is-name', 'content' => 'Imię Nazwisko']],
        ['core/paragraph', ['className' => 'is-role', 'content' => 'Stanowisko, Firma']],
    ],
]);
