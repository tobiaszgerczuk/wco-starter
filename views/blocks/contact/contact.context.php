<?php

return static function (array $fields, string $content): array {
    $shortcode = trim((string) ($fields['contact_form_shortcode'] ?? ''));

    // Only a single shortcode is rendered, e.g. [contact-form-7 id="123" title="Kontakt"].
    return [
        'form_html' => preg_match('/^\[[a-z0-9_-]+(?:\s[^\]]*)?\]$/i', $shortcode) === 1 ? do_shortcode($shortcode) : '',
    ];
};
