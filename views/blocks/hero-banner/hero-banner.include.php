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
    ['block-hero-banner', !empty($context['block']['align']) ? 'align' . $context['block']['align'] : '']
);
$context['section_id'] = SectionSettings::section_id($context['fields']);
$context['section_style'] = SectionSettings::inline_style($context['fields']);

Timber::render('blocks/hero-banner/hero-banner.twig', $context);
