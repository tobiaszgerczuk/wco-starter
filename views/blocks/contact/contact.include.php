<?php

use WCO\Starter\Blocks\BlockContext;

$fields = get_fields() ?: [];
$shortcode = trim((string) ($fields['contact_form_shortcode'] ?? ''));
// Only a single shortcode is rendered, e.g. [contact-form-7 id="123" title="Kontakt"].
$form_html = preg_match('/^\[[a-z0-9_-]+(?:\s[^\]]*)?\]$/i', $shortcode) === 1 ? do_shortcode($shortcode) : '';

BlockContext::render('contact', $block ?? [], $content ?? '', $is_preview ?? false, [
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
    'form_html' => $form_html,
]);
