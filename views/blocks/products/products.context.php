<?php

use WCO\Starter\Core\Shop;

return static function (array $fields, string $content): array {
    if (!Shop::active()) {
        return ['products' => []];
    }

    $products = Shop::query([
        'source' => (string) ($fields['products_source'] ?? 'latest'),
        'count' => (int) ($fields['products_count'] ?? 4),
        'category' => (int) ($fields['products_category'] ?? 0),
    ]);

    return ['products' => Shop::cards($products), 'shop_url' => wc_get_page_permalink('shop')];
};
