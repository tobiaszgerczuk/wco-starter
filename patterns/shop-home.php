<?php
/**
 * Title: Shop home
 * Slug: wco-starter/shop-home
 * Description: Shop landing page: hero, product categories, bestsellers, benefits and a call to action.
 * Categories: wco-sections
 * Keywords: shop, woocommerce, sklep, home
 */

defined('ABSPATH') || exit;
?>
<!-- wp:acf/hero {"hero_min_height":"md","section_space_top":"lg","section_space_bottom":"lg"} -->
<!-- wp:group {"className":"block-hero__text"} -->
<div class="wp-block-group block-hero__text"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Nowa kolekcja</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Produkty, które pokochasz od pierwszego dnia</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Starannie dobrana oferta, szybka wysyłka i 30 dni na zwrot. Zobacz, co przygotowaliśmy dla Ciebie.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button  -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Przejdź do sklepu</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Promocje</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- /wp:acf/hero -->

<!-- wp:acf/product-categories {"categories_count":4,"categories_columns":"4"} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Kategorie</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Kupuj według kategorii</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Wybierz dział i znajdź to, czego potrzebujesz.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:acf/product-categories -->

<!-- wp:acf/products {"products_source":"bestselling","products_count":4,"products_columns":"4","section_background_color":"#f7f3eb"} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Bestsellery</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Najczęściej wybierane</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Produkty, które klienci kupują najchętniej.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:acf/products -->

<!-- wp:acf/features {"features_columns":"3"} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Dlaczego my</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Zakupy bez stresu</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Trzy rzeczy, na których możesz polegać.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:acf/feature-item -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Szybka wysyłka</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Zamówienia złożone do 14:00 wysyłamy jeszcze tego samego dnia.</p>
<!-- /wp:paragraph -->
<!-- /wp:acf/feature-item -->
<!-- wp:acf/feature-item -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">30 dni na zwrot</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Nie pasuje? Oddasz produkt bez podawania przyczyny.</p>
<!-- /wp:paragraph -->
<!-- /wp:acf/feature-item -->
<!-- wp:acf/feature-item -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Bezpieczne płatności</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Szybkie przelewy, BLIK i karta, wszystko w jednym miejscu.</p>
<!-- /wp:paragraph -->
<!-- /wp:acf/feature-item -->
<!-- /wp:acf/features -->

<!-- wp:acf/products {"products_source":"sale","products_count":4,"products_columns":"4"} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Promocje</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Teraz taniej</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Sprawdź produkty z obniżoną ceną.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:acf/products -->

<!-- wp:acf/cta {"cta_align":"center","cta_boxed":true} -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Masz pytania? Jesteśmy do dyspozycji</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Napisz do nas, a odpowiemy w ciągu jednego dnia roboczego.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button  -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Skontaktuj się</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
<!-- /wp:acf/cta -->
