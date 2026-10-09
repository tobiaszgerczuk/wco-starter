<?php

use WCO\Starter\Blocks\BlockContext;

BlockContext::render('separator', $block ?? [], $content ?? '', $is_preview ?? false, [
    'inner_template' => [],
]);
