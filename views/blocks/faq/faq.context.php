<?php

return static function (array $fields, string $content): array {
    if (empty($fields['faq_schema']) || $content === '') {
        return ['faq_schema' => ''];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $content . '</div>');
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $entities = [];
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " block-faq-item ")]') as $item) {
        $question = '';
        $answer = '';
        // The heading and its answer are siblings inside .acf-innerblocks-container.
        $heading = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $item)->item(0);
        foreach ($heading ? $heading->parentNode->childNodes : [] as $node) {
            if ($question === '' && $node instanceof DOMElement && preg_match('/^h[1-6]$/i', $node->nodeName)) {
                $question = trim($node->textContent);
                continue;
            }
            $answer .= $dom->saveHTML($node);
        }
        if ($question !== '' && trim(wp_strip_all_tags($answer)) !== '') {
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_kses_post($answer)],
            ];
        }
    }

    if (!$entities) {
        return ['faq_schema' => ''];
    }

    return [
        'faq_schema' => wp_json_encode(
            ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        ),
    ];
};
