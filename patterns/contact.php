<?php
/**
 * Title: Contact page
 * Slug: wco-starter/contact
 * Description: Heading, contact details with a form, and a map.
 * Categories: wco-sections
 * Keywords: contact, section, page
 */

defined('ABSPATH') || exit;
?>
<!-- wp:acf/section-heading {"name":"acf/section-heading","mode":"preview"} -->
<!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Kontakt</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Porozmawiajmy</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Wypełnij formularz albo skorzystaj z danych kontaktowych.</p>
<!-- /wp:paragraph -->
<!-- /wp:acf/section-heading -->

<!-- wp:acf/contact {"name":"acf/contact","mode":"preview"} -->
<!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Dane kontaktowe</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Napisz do nas</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Odpowiemy najszybciej, jak to możliwe.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"is-contact-list"} -->
<ul class="wp-block-list is-contact-list"><!-- wp:list-item -->
<li>ul. Przykładowa 1, 00-001 Warszawa</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>+48 123 456 789</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>kontakt@example.com</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- /wp:acf/contact -->

<!-- wp:acf/map {"name":"acf/map","mode":"preview","data":{"map_address":"Plac Defilad 1, Warszawa","_map_address":"field_map_address","map_height":"400","_map_height":"field_map_height"}} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Mapa</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Gdzie jesteśmy</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Zapraszamy do naszego biura.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:acf/map -->
