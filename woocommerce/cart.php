<?php
/**
 * Template for WooCommerce cart
 */
use Timber\Timber;

$context = Timber::context();
ob_start();
woocommerce_cart();
$context['content'] = ob_get_clean();

Timber::render('woocommerce/cart.twig', $context);
