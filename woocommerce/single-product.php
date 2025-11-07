<?php
/**
 * Template for single product
 */
use Timber\Timber;

$context = Timber::context();
$context['post'] = Timber::get_post();
$context['product'] = wc_get_product($context['post']->ID);

Timber::render('woocommerce/single-product.twig', $context);
