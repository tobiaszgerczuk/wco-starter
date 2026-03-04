<?php

use Timber\Timber;
use WCO\Starter\Blocks\SectionSettings;

$context = Timber::context();
$context['fields'] = get_fields() ?: [];
$context['block'] = $block ?? [];
$context['is_preview'] = $is_preview ?? false;
$context['post_id'] = $post_id ?? 0;
$context['section_classes'] = SectionSettings::build_classes(
    $context['fields'],
    ['block-text-image', !empty($context['block']['align']) ? 'align' . $context['block']['align'] : '']
);

Timber::render('blocks/text-image/text-image.twig', $context);
