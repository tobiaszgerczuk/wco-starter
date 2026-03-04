<?php

use Timber\Timber;

$context = Timber::context();
$context['fields'] = get_fields() ?: [];
$context['block'] = $block ?? [];
$context['is_preview'] = $is_preview ?? false;
$context['post_id'] = $post_id ?? 0;

Timber::render('blocks/hero-banner/hero-banner.twig', $context);
