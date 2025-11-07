<?php
/**
 * Template for WooCommerce checkout
 */
use Timber\Timber;

$context = Timber::context();
ob_start();
woocommerce_checkout();
$context['content'] = ob_get_clean();

Timber::render('woocommerce/checkout.twig', $context);
