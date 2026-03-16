<?php

use Timber\Timber;

$fields = get_fields() ?: [];

$resolve_spacer_value = static function (string $modeKey, string $scaleKey, string $customKey, string $defaultScale) use ($fields): string {
    $scaleMap = [
        'none' => 'var(--section-space-none)',
        'xs' => 'var(--section-space-xs)',
        'sm' => 'var(--section-space-sm)',
        'md' => 'var(--section-space-md)',
        'lg' => 'var(--section-space-lg)',
        'xl' => 'var(--section-space-xl)',
    ];

    $mode = is_string($fields[$modeKey] ?? null) ? $fields[$modeKey] : 'scale';

    if ($mode === 'custom') {
        $custom = isset($fields[$customKey]) ? (int) $fields[$customKey] : 0;
        if ($custom > 0) {
            return $custom . 'px';
        }
    }

    $scale = is_string($fields[$scaleKey] ?? null) ? $fields[$scaleKey] : $defaultScale;

    return $scaleMap[$scale] ?? $scaleMap[$defaultScale];
};

$desktopHeight = $resolve_spacer_value('spacer_desktop_mode', 'spacer_desktop_size', 'spacer_desktop_custom', 'md');
$mobileHeight = $resolve_spacer_value('spacer_mobile_mode', 'spacer_mobile_size', 'spacer_mobile_custom', 'sm');

$context = Timber::context();
$context['fields'] = $fields;
$context['block'] = $block ?? [];
$context['is_preview'] = $is_preview ?? false;
$context['post_id'] = $post_id ?? 0;
$context['spacer_style'] = sprintf(
    '--spacer-height-desktop: %1$s; --spacer-height-mobile: %2$s;',
    $desktopHeight,
    $mobileHeight
);
$context['spacer_label'] = sprintf('Desktop: %s / Mobile: %s', $desktopHeight, $mobileHeight);

Timber::render('blocks/spacer/spacer.twig', $context);
