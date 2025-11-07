<?php
use Timber\Timber;

$context = Timber::context();

// Pobierz tytuł archiwum
if (is_category()) {
    $context['archive_title'] = single_cat_title('', false);
} elseif (is_tag()) {
    $context['archive_title'] = single_tag_title('', false);
} elseif (is_author()) {
    $context['archive_title'] = get_the_author();
} elseif (is_post_type_archive()) {
    $context['archive_title'] = post_type_archive_title('', false);
} elseif (is_tax()) {
    $context['archive_title'] = single_term_title('', false);
} else {
    $context['archive_title'] = __('Archiwum', 'wco-starter');
}

$context['posts'] = Timber::get_posts();

Timber::render('archive.twig', $context);