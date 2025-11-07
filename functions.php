<?php
require_once __DIR__ . '/vendor/autoload.php';

if (!class_exists('Timber\Timber')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>Timber not installed. Run <code>composer install</code></p></div>';
    });
    return;
}

// Debug: Check if your class exists
if (!class_exists('WCO\Starter\Core\Theme')) {
    error_log('WCO\Starter\Core\Theme not found! Check PSR-4 path: app/Core/Theme.php');
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Class WCO\Starter\Core\Theme not found!</strong><br>
        Check: <code>app/Core/Theme.php</code> exists and namespace is correct.<br>
        Run: <code>composer dump-autoload -o</code></p></div>';
    });
    return;
}

WCO\Starter\Core\Theme::init();
