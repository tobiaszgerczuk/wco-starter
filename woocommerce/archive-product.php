<?php
/**
 * Template for WooCommerce product archive
 */
use Timber\Timber;

$context = Timber::context();
$context['title'] = post_type_archive_title('', false);
$context['products'] = Timber::get_posts([
  'post_type' => 'product',
  'posts_per_page' => -1
]);

Timber::render('woocommerce/archive-product.twig', $context);
