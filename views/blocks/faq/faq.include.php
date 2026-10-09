<?php

use WCO\Starter\Blocks\BlockContext;

$schema = '';
$fields = get_fields() ?: [];
if (!($is_preview ?? false) && !empty($fields['faq_schema']) && !empty($content)) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $content . '</div>');
    libxml_clear_errors();

    $entities = [];
    foreach ((new DOMXPath($dom))->query('//*[contains(concat(" ", normalize-space(@class), " "), " block-faq-item ")]') as $item) {
        $question = '';
        $answer = '';
        // The heading and its answer are siblings inside .acf-innerblocks-container.
        $heading = (new DOMXPath($dom))->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $item)->item(0);
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

    if ($entities) {
        $schema = wp_json_encode(
            ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );
    }
}

BlockContext::render('faq', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
        ...array_fill(0, 3, ['acf/faq-item', []]),
    ],
    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/faq-item'],
    'faq_schema' => $schema,
]);
