<?php
/**
 * Shop archive: main shop page, product categories and tags, product search.
 */
use Timber\Timber;
use WCO\Starter\Core\Shop;

$context = Timber::context();

$products = [];
while (have_posts()) {
    the_post();
    $product = wc_get_product(get_the_ID());
    if ($product) {
        $products[] = $product;
    }
}
wp_reset_postdata();

$context['title'] = woocommerce_page_title(false);
$context['description'] = is_tax() ? term_description() : '';
$context['products'] = Shop::cards($products);
$context['chips'] = Shop::category_chips();
$context['shop_url'] = wc_get_page_permalink('shop');
$context['notices'] = Shop::capture('wc_print_notices');
$context['result_count'] = Shop::capture('woocommerce_result_count');
$context['ordering'] = Shop::capture('woocommerce_catalog_ordering');
$context['pagination'] = Shop::capture('woocommerce_pagination');

Timber::render('woocommerce/archive-product.twig', $context);
