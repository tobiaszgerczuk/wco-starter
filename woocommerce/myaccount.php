<?php
/**
 * Template for WooCommerce my account page
 */
use Timber\Timber;

$context = Timber::context();
ob_start();
woocommerce_account_content();
$context['content'] = ob_get_clean();

Timber::render('woocommerce/myaccount.twig', $context);
