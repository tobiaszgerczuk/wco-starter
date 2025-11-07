<?php
use Timber\Timber;
use Timber\PostQuery;

$context = Timber::context();

// Correct: Pass the current WP query (or custom query)
$context['posts'] = new PostQuery($GLOBALS['wp_query']);

Timber::render(['index.twig'], $context);