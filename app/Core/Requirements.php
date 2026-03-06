<?php

namespace WCO\Starter\Core;

class Requirements
{
    private static bool $booted = false;
    private static bool $blockingErrors = false;
    private static array $messages = [];

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        self::evaluate();
        add_action('admin_notices', [self::class, 'render_notice']);
    }

    public static function has_blocking_errors(): bool
    {
        return self::$blockingErrors;
    }

    public static function render_notice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (self::$messages === []) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return;
        }

        $hasBlocking = false;
        $hasWarning = false;
        $hasInfo = false;

        foreach (self::$messages as $message) {
            if ($message['type'] === 'error') {
                $hasBlocking = true;
            } elseif ($message['type'] === 'warning') {
                $hasWarning = true;
            } else {
                $hasInfo = true;
            }
        }

        if (!$hasBlocking && !$hasWarning && $screen->id !== 'themes') {
            return;
        }

        $noticeClass = $hasBlocking
            ? 'notice notice-error'
            : ($hasWarning ? 'notice notice-warning' : 'notice notice-info');

        echo '<div class="' . esc_attr($noticeClass) . '"><p><strong>WCO Starter requirements</strong></p><ul>';
        foreach (self::$messages as $message) {
            $label = strtoupper($message['type']);
            echo '<li><strong>' . esc_html($label . ':') . '</strong> ' . esc_html($message['message']) . '</li>';
        }
        echo '</ul></div>';
    }

    private static function evaluate(): void
    {
        self::$messages = [];
        self::$blockingErrors = false;

        if (!class_exists('Timber\Timber')) {
            self::$blockingErrors = true;
            self::$messages[] = [
                'type' => 'error',
                'message' => 'Timber is not available. Install dependencies (`composer install`) and activate Timber if required by your stack.',
            ];
        }

        if (!function_exists('acf_register_block_type')) {
            $acfMessage = class_exists('ACF')
                ? 'ACF is active, but ACF Pro block API is unavailable. Custom ACF blocks are disabled.'
                : 'ACF Pro is not active. Custom ACF blocks are disabled.';

            self::$messages[] = [
                'type' => 'warning',
                'message' => $acfMessage,
            ];
        } else {
            $syncStatus = Acf::field_group_sync_status();
            if (!empty($syncStatus['missing_in_db'])) {
                self::$messages[] = [
                    'type' => 'warning',
                    'message' => 'Some local ACF JSON groups are not in DB: ' . implode(', ', $syncStatus['missing_in_db']) . '. Sync them in ACF Tools (Local JSON sync).',
                ];
            }

            if (!empty($syncStatus['missing_in_local'])) {
                self::$messages[] = [
                    'type' => 'warning',
                    'message' => 'Some ACF groups exist in DB only: ' . implode(', ', $syncStatus['missing_in_local']) . '. Run `npm run acf:pull`.',
                ];
            }

            if (!empty($syncStatus['local_ahead']) || !empty($syncStatus['db_ahead'])) {
                $delta = [];
                if (!empty($syncStatus['local_ahead'])) {
                    $delta[] = 'Local JSON newer: ' . implode(', ', $syncStatus['local_ahead']) . ' (sync from ACF tools).';
                }

                if (!empty($syncStatus['db_ahead'])) {
                    $delta[] = 'DB newer: ' . implode(', ', $syncStatus['db_ahead']) . ' (run `npm run acf:pull`).';
                }

                self::$messages[] = [
                    'type' => 'info',
                    'message' => implode(' ', $delta),
                ];
            }
        }

        if (!class_exists('WooCommerce')) {
            self::$messages[] = [
                'type' => 'info',
                'message' => 'WooCommerce is optional and currently inactive. Shop templates and cart API routes are disabled.',
            ];
        }
    }
}
