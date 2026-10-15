<?php

namespace WCO\Starter\Core;

/**
 * One-click demo of the shop variant: categories, products with generated placeholder images,
 * a home page built from the shop-home pattern, a main menu and a few settings.
 *
 * Everything it creates is tagged with the `_wco_demo` meta, so "Remove demo data" can undo the
 * import without touching content created by hand.
 */
class DemoImport
{
    private const META = '_wco_demo';
    private const OPTION = 'wco_starter_demo';
    private const IMPORT_ACTION = 'wco_starter_import_demo';
    private const REMOVE_ACTION = 'wco_starter_remove_demo';
    private const PAGE = 'wco-theme-demo';

    /** slug => [name, rgb] */
    private const CATEGORIES = [
        'odziez' => ['Odzież', [90, 178, 94]],
        'akcesoria' => ['Akcesoria', [122, 89, 55]],
        'dom' => ['Dom', [205, 174, 127]],
        'kosmetyki' => ['Kosmetyki', [74, 94, 140]],
    ];

    /** [name, category, regular, sale, featured, sales, stock, short description] */
    private const PRODUCTS = [
        ['Bluza oversize', 'odziez', '189.00', '', true, 48, true, 'Miękka bluza z bawełny organicznej o luźnym kroju.'],
        ['Koszulka basic', 'odziez', '79.00', '59.00', false, 112, true, 'Gładka koszulka na co dzień, 100% bawełny.'],
        ['Spodnie cargo', 'odziez', '229.00', '', false, 31, true, 'Wygodne spodnie z dużymi kieszeniami.'],
        ['Czapka z daszkiem', 'akcesoria', '69.00', '', true, 64, true, 'Regulowana czapka z haftowanym logo.'],
        ['Plecak miejski', 'akcesoria', '259.00', '199.00', true, 87, true, 'Wodoodporny plecak z komorą na laptopa 15".'],
        ['Portfel skórzany', 'akcesoria', '149.00', '', false, 22, false, 'Ręcznie szyty portfel z naturalnej skóry.'],
        ['Kubek ceramiczny', 'dom', '49.00', '', false, 140, true, 'Ceramiczny kubek 350 ml, bezpieczny do zmywarki.'],
        ['Koc bawełniany', 'dom', '199.00', '159.00', true, 40, true, 'Duży, ciepły koc w stylu skandynawskim.'],
        ['Świeca sojowa', 'dom', '59.00', '', false, 95, true, 'Naturalna świeca o zapachu cedru i wanilii.'],
        ['Krem do rąk', 'kosmetyki', '39.00', '', false, 130, true, 'Odżywczy krem z masłem shea.'],
        ['Serum do twarzy', 'kosmetyki', '129.00', '', true, 58, true, 'Lekkie serum nawilżające z kwasem hialuronowym.'],
        ['Mydło naturalne', 'kosmetyki', '24.00', '19.00', false, 73, true, 'Ręcznie robione mydło z olejem kokosowym.'],
    ];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_page']);
        add_action('admin_post_' . self::IMPORT_ACTION, [self::class, 'handle_import']);
        add_action('admin_post_' . self::REMOVE_ACTION, [self::class, 'handle_remove']);
    }

    public static function register_page(): void
    {
        add_submenu_page(Settings::PAGE, 'Demo sklepu', 'Demo sklepu', 'manage_options', self::PAGE, [self::class, 'render_page']);
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = get_option(self::OPTION, []);
        $imported = !empty($state['imported_at']);

        echo '<div class="wrap"><h1>Demo sklepu</h1>';
        if (isset($_GET['done'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $messages = ['import' => 'Demo zostało zaimportowane.', 'remove' => 'Dane demo zostały usunięte.'];
            $key = sanitize_key((string) $_GET['done']); // phpcs:ignore WordPress.Security.NonceVerification
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$key] ?? 'Gotowe.') . '</p></div>';
        }

        if (!Shop::active()) {
            echo '<div class="notice notice-warning"><p>Do importu demo potrzebna jest aktywna wtyczka WooCommerce.</p></div></div>';

            return;
        }

        echo '<p>Import tworzy 4 kategorie, 12 produktów z grafikami zastępczymi, stronę główną sklepu ze wzorca <em>Shop home</em> i menu główne. Zaimportowane elementy możesz w całości usunąć jednym przyciskiem; treści dodane ręcznie nie są ruszane.</p>';
        if ($imported) {
            echo '<p><strong>Demo zaimportowano ' . esc_html(wp_date('j F Y, H:i', (int) $state['imported_at'])) . '.</strong> Ponowny import pomija już istniejące elementy.</p>';
        }

        foreach ([[self::IMPORT_ACTION, $imported ? 'Zaimportuj ponownie' : 'Zaimportuj demo', 'primary'], [self::REMOVE_ACTION, 'Usuń dane demo', 'secondary']] as [$action, $label, $type]) {
            if ($action === self::REMOVE_ACTION && !$imported) {
                continue;
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:12px">';
            wp_nonce_field($action);
            echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
            submit_button($label, $type, 'submit', false);
            echo '</form>';
        }
        echo '</div>';
    }

    public static function handle_import(): void
    {
        self::guard(self::IMPORT_ACTION);
        self::run();
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'done' => 'import'], admin_url('admin.php')));
        exit;
    }

    public static function handle_remove(): void
    {
        self::guard(self::REMOVE_ACTION);
        self::remove();
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'done' => 'remove'], admin_url('admin.php')));
        exit;
    }

    private static function guard(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', '', ['response' => 403]);
        }
        check_admin_referer($action);
    }

    // ------------------------------------------------------------------ import

    /**
     * @return array<string, int> Counts of created items.
     */
    public static function run(): array
    {
        if (!Shop::active()) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $created = ['categories' => 0, 'products' => 0, 'pages' => 0];
        $categoryIds = [];

        foreach (self::CATEGORIES as $slug => [$name, $rgb]) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if (!$term) {
                $result = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
                if (is_wp_error($result)) {
                    continue;
                }
                $termId = (int) $result['term_id'];
                update_term_meta($termId, self::META, 1);
                $imageId = self::placeholder($name, $rgb, 'cat-' . $slug);
                if ($imageId) {
                    update_term_meta($termId, 'thumbnail_id', $imageId);
                }
                $created['categories']++;
            } else {
                $termId = $term->term_id;
            }
            $categoryIds[$slug] = $termId;
        }

        foreach (self::PRODUCTS as $index => [$name, $category, $regular, $sale, $featured, $sales, $inStock, $short]) {
            $slug = sanitize_title($name);
            if (get_page_by_path($slug, OBJECT, 'product')) {
                continue;
            }

            $product = new \WC_Product_Simple();
            $product->set_name($name);
            $product->set_slug($slug);
            $product->set_status('publish');
            $product->set_regular_price($regular);
            if ($sale !== '') {
                $product->set_sale_price($sale);
            }
            $product->set_featured($featured);
            $product->set_short_description($short);
            $product->set_description($short . ' Ten opis to treść demonstracyjna: zastąp ją własnym opisem produktu, parametrami i informacjami o wysyłce.');
            $product->set_sku('DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
            $product->set_manage_stock(true);
            $product->set_stock_quantity($inStock ? 25 : 0);
            $product->set_stock_status($inStock ? 'instock' : 'outofstock');
            if (isset($categoryIds[$category])) {
                $product->set_category_ids([$categoryIds[$category]]);
            }
            $rgb = self::CATEGORIES[$category][1];
            $imageId = self::placeholder($name, $rgb, 'product-' . $slug);
            if ($imageId) {
                $product->set_image_id($imageId);
            }

            $productId = $product->save();
            update_post_meta($productId, 'total_sales', $sales);
            update_post_meta($productId, self::META, 1);
            $created['products']++;
        }

        $homeId = self::home_page($created);
        $menuId = self::main_menu($homeId);

        $previous = get_option(self::OPTION, []);
        update_option(self::OPTION, [
            'imported_at' => time(),
            'home_id' => $homeId,
            'menu_id' => $menuId,
            'previous_front' => $previous['previous_front'] ?? [
                'show_on_front' => get_option('show_on_front'),
                'page_on_front' => (int) get_option('page_on_front'),
            ],
        ], false);

        return $created;
    }

    /** @param array<string, int> $created */
    private static function home_page(array &$created): int
    {
        $existing = get_page_by_path('strona-glowna');
        if ($existing) {
            $id = (int) $existing->ID;
        } else {
            $content = self::pattern('shop-home');
            $heroId = self::placeholder('Hero', [90, 178, 94], 'hero-shop');
            if ($heroId) {
                $url = (string) wp_get_attachment_image_url($heroId, 'large');
                $content = str_replace(
                    '<figure class="wp-block-image"><img alt=""/></figure>',
                    '<figure class="wp-block-image size-large"><img src="' . esc_url($url) . '" alt="" class="wp-image-' . $heroId . '"/></figure>',
                    $content
                );
                $content = str_replace('<!-- wp:image -->', '<!-- wp:image {"id":' . $heroId . ',"sizeSlug":"large"} -->', $content);
            }

            $id = (int) wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Strona główna',
                'post_name' => 'strona-glowna',
                'post_content' => wp_slash($content),
            ]);
            if ($id) {
                update_post_meta($id, self::META, 1);
                $created['pages']++;
            }
        }

        if ($id) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $id);
        }

        return $id;
    }

    private static function main_menu(int $homeId): int
    {
        $menu = wp_get_nav_menu_object('Menu główne (demo)');
        if ($menu) {
            return (int) $menu->term_id;
        }

        $menuId = wp_create_nav_menu('Menu główne (demo)');
        if (is_wp_error($menuId)) {
            return 0;
        }

        $pages = array_filter([$homeId, (int) wc_get_page_id('shop'), (int) wc_get_page_id('myaccount')], static fn (int $id): bool => $id > 0);
        foreach ($pages as $pageId) {
            wp_update_nav_menu_item($menuId, 0, [
                'menu-item-object-id' => $pageId,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
            ]);
        }

        $locations = get_theme_mod('nav_menu_locations', []);
        $locations['main-menu'] = $menuId;
        $locations['footer-menu'] = $menuId;
        set_theme_mod('nav_menu_locations', $locations);

        return (int) $menuId;
    }

    private static function pattern(string $name): string
    {
        $file = get_template_directory() . '/patterns/' . $name . '.php';
        if (!is_file($file)) {
            return '';
        }

        ob_start();
        include $file;

        return trim((string) ob_get_clean());
    }

    /**
     * Draws a soft gradient with the item name and stores it as a media library attachment.
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private static function placeholder(string $label, array $rgb, string $filename): int
    {
        if (!function_exists('imagecreatetruecolor')) {
            return 0;
        }

        $size = 800;
        $image = imagecreatetruecolor($size, $size);
        for ($y = 0; $y < $size; $y++) {
            $mix = 0.05 + 0.35 * ($y / $size);
            $color = imagecolorallocate(
                $image,
                (int) ($rgb[0] + (255 - $rgb[0]) * $mix),
                (int) ($rgb[1] + (255 - $rgb[1]) * $mix),
                (int) ($rgb[2] + (255 - $rgb[2]) * $mix)
            );
            imageline($image, 0, $y, $size, $y, $color);
        }

        // Soft shapes so the tile reads as a product picture, not an empty box.
        imagealphablending($image, true);
        imagefilledellipse($image, (int) ($size * 0.5), (int) ($size * 0.46), 360, 360, imagecolorallocatealpha($image, 255, 255, 255, 70));
        imagefilledellipse($image, (int) ($size * 0.5), (int) ($size * 0.46), 200, 200, imagecolorallocatealpha($image, 255, 255, 255, 40));
        imagefilledrectangle($image, (int) ($size * 0.3), (int) ($size * 0.82), (int) ($size * 0.7), (int) ($size * 0.84), imagecolorallocatealpha($image, 255, 255, 255, 60));

        ob_start();
        imagejpeg($image, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $upload = wp_upload_bits($filename . '.jpg', null, $bytes);
        if (!empty($upload['error'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/jpeg',
            'post_title' => $label,
            'post_status' => 'inherit',
        ], $upload['file']);
        if (is_wp_error($attachmentId) || !$attachmentId) {
            return 0;
        }

        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $upload['file']));
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $label);
        update_post_meta($attachmentId, self::META, 1);

        return (int) $attachmentId;
    }

    // ------------------------------------------------------------------ removal

    public static function remove(): void
    {
        $state = get_option(self::OPTION, []);

        $ids = get_posts([
            'post_type' => ['product', 'page', 'attachment'],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => self::META,
        ]);
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
        }

        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_key' => self::META]);
        foreach (is_wp_error($terms) ? [] : $terms as $term) {
            wp_delete_term($term->term_id, 'product_cat');
        }

        if (!empty($state['menu_id'])) {
            wp_delete_nav_menu((int) $state['menu_id']);
        }

        $previous = $state['previous_front'] ?? null;
        if (is_array($previous)) {
            update_option('show_on_front', $previous['show_on_front'] ?: 'posts');
            update_option('page_on_front', (int) $previous['page_on_front']);
        }

        delete_option(self::OPTION);
    }
}
