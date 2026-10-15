<?php

namespace WCO\Starter\Core;

/**
 * WooCommerce helpers for the theme templates: product card data for Twig, shop loop settings
 * and rendered WooCommerce fragments (ordering, result count, pagination, notices).
 */
class Shop
{
    public static function boot(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('loop_shop_columns', static fn (): int => 4);
        add_filter('loop_shop_per_page', static fn (): int => 12);
        add_filter('woocommerce_enqueue_styles', static function (array $styles): array {
            // The theme styles the shop itself (assets/scss/woocommerce); the layout sheet only fights it.
            unset($styles['woocommerce-layout'], $styles['woocommerce-smallscreen']);

            return $styles;
        });
    }

    public static function active(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_product');
    }

    /**
     * Everything product-card.twig needs, so templates never call WooCommerce directly.
     *
     * @return array<string, mixed>
     */
    public static function card(\WC_Product $product): array
    {
        $onSale = $product->is_on_sale();
        $percent = 0;
        if ($onSale && $product->is_type('simple') && (float) $product->get_regular_price() > 0) {
            $percent = (int) round((1 - (float) $product->get_sale_price() / (float) $product->get_regular_price()) * 100);
        }

        $ratingCount = $product->get_rating_count();
        $categories = wc_get_product_category_list($product->get_id(), ', ', '', '');
        $categoryIds = $product->get_category_ids();
        $category = $categoryIds ? get_term($categoryIds[0], 'product_cat') : null;

        return [
            'id' => $product->get_id(),
            'title' => $product->get_name(),
            'url' => $product->get_permalink(),
            'image' => $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']),
            'price_html' => $product->get_price_html(),
            'on_sale' => $onSale,
            'sale_percent' => $percent,
            'rating_html' => $ratingCount > 0 ? wc_get_rating_html((float) $product->get_average_rating(), $ratingCount) : '',
            'in_stock' => $product->is_in_stock(),
            'ajax_add' => $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock(),
            'cta_label' => $product->is_in_stock() ? $product->add_to_cart_text() : __('Read more', 'woocommerce'),
            'cta_url' => $product->is_in_stock() ? $product->add_to_cart_url() : $product->get_permalink(),
            'category' => $category && !is_wp_error($category) ? $category->name : '',
            'categories_html' => $categories,
        ];
    }

    /**
     * @param array<int, \WC_Product|int|\WP_Post> $products
     * @return list<array<string, mixed>>
     */
    public static function cards(array $products): array
    {
        $cards = [];
        foreach ($products as $item) {
            $product = $item instanceof \WC_Product ? $item : wc_get_product($item);
            if ($product instanceof \WC_Product && $product->is_visible()) {
                $cards[] = self::card($product);
            }
        }

        return $cards;
    }

    /**
     * Top-level product categories for the shop filter chips.
     *
     * @return list<array{name: string, url: string, count: int, active: bool}>
     */
    public static function category_chips(): array
    {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => [(int) get_option('default_product_cat')]]);
        if (is_wp_error($terms)) {
            return [];
        }

        $current = is_tax('product_cat') ? get_queried_object_id() : 0;
        $chips = [];
        foreach ($terms as $term) {
            $chips[] = ['name' => $term->name, 'url' => (string) get_term_link($term), 'count' => (int) $term->count, 'active' => $current === $term->term_id];
        }

        return $chips;
    }

    /** Output of a WooCommerce template function captured as a string. */
    public static function capture(callable $callback, mixed ...$args): string
    {
        ob_start();
        $callback(...$args);

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $options Query options: source, count, category.
     * @return list<\WC_Product>
     */
    public static function query(array $options): array
    {
        $args = [
            'status' => 'publish',
            'limit' => max(1, min(24, (int) ($options['count'] ?? 4))),
            'visibility' => 'catalog',
            'return' => 'objects',
        ];

        switch ($options['source'] ?? 'latest') {
            case 'featured':
                $args['featured'] = true;
                break;
            case 'sale':
                $args['include'] = array_merge([0], wc_get_product_ids_on_sale());
                break;
            case 'bestselling':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                $args['order'] = 'DESC';
                break;
            case 'category':
                $term = get_term((int) ($options['category'] ?? 0), 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $args['category'] = [$term->slug];
                }
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
        }

        $products = wc_get_products($args);

        return is_array($products) ? $products : [];
    }
}
