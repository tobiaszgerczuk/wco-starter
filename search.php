<?php
use Timber\Timber;

$context = Timber::context();
$context['posts'] = Timber::get_posts();
$context['query'] = get_search_query();
Timber::render(['search.twig'], $context);
