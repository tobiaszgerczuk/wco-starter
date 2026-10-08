<?php
/**
 * Title: Blog intro
 * Slug: wco-starter/blog-intro
 * Description: Heading with the latest posts and a call to action.
 * Categories: wco-sections
 * Keywords: blog-intro, section, page
 */

defined('ABSPATH') || exit;
?>
<!-- wp:acf/latest-posts {"name":"acf/latest-posts","mode":"preview","data":{"latest_posts_count":3,"_latest_posts_count":"field_latest_posts_count","latest_posts_columns":"3","_latest_posts_columns":"field_latest_posts_columns"}} -->
<!-- wp:group {"className":"is-span-all block-head"} -->
<div class="wp-block-group is-span-all block-head"><!-- wp:paragraph {"className":"is-eyebrow"} -->
<p class="is-eyebrow">Blog</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Z naszego bloga</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Najnowsze wpisy i porady.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:acf/latest-posts -->

<!-- wp:acf/cta {"name":"acf/cta","mode":"preview","data":{"cta_boxed":true,"_cta_boxed":"field_cta_boxed","section_background_color":"#f7f3eb","_section_background_color":"field_section_background_color"}} -->
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Bądź na bieżąco</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-lead"} -->
<p class="is-lead">Zapisz się, aby nie przegapić nowych wpisów.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Zapisz się</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:acf/cta -->
