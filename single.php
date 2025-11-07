<?php
use Timber\Timber;
use Timber\Post;

$context = Timber::context();
$context['post'] = Timber::get_post();
Timber::render(['single.twig'], $context);
