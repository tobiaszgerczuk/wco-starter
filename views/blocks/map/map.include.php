<?php

use WCO\Starter\Blocks\BlockContext;

$fields = get_fields() ?: [];
$address = trim((string) ($fields['map_address'] ?? ''));
$custom = trim((string) ($fields['map_embed_url'] ?? ''));
$height = in_array((int) ($fields['map_height'] ?? 0), [300, 400, 500, 600], true) ? (int) $fields['map_height'] : 400;

$src = '';
if ($custom !== '' && preg_match('~^https://(www\.google\.com/maps|maps\.google\.com|www\.openstreetmap\.org|mapy\.cz|en\.mapy\.cz)[/?]~i', $custom) === 1) {
    $src = $custom;
} elseif ($address !== '') {
    $src = 'https://www.google.com/maps?q=' . rawurlencode($address) . '&output=embed&hl=pl';
}

$map = [
    'src' => esc_url($src),
    'height' => $height,
    'title' => $address !== '' ? 'Mapa: ' . $address : 'Mapa',
    'link' => $address !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address) : '',
];

BlockContext::render('map', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [
        BlockContext::head_template(),
    ],
    'inner_allowed' => ['core/heading', 'core/paragraph'],
    'map' => $map,
]);
