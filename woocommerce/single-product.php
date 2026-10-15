<?php
/**
 * Single product.
 */
use Timber\Timber;
use WCO\Starter\Core\Shop;

$context = Timber::context();

the_post();
global $product;
$product = wc_get_product(get_the_ID());

$related = array_slice(array_map('wc_get_product', wc_get_related_products($product->get_id(), 4)), 0, 4);

$context['product'] = Shop::card($product);
$context['product_id'] = $product->get_id();
$context['product_class'] = implode(' ', wc_get_product_class('single-product__item', $product));
$context['notices'] = Shop::capture('wc_print_notices');
$context['gallery'] = Shop::capture('woocommerce_show_product_images');
$context['rating'] = Shop::capture('woocommerce_template_single_rating');
$context['excerpt'] = Shop::capture('woocommerce_template_single_excerpt');
$context['add_to_cart'] = Shop::capture('woocommerce_template_single_add_to_cart');
$context['meta'] = Shop::capture('woocommerce_template_single_meta');
$context['tabs'] = Shop::capture('woocommerce_output_product_data_tabs');
$context['related'] = Shop::cards(array_filter($related));
$context['breadcrumbs'] = Shop::capture('woocommerce_breadcrumb');

Timber::render('woocommerce/single-product.twig', $context);
