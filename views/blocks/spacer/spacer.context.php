<?php

return static function (array $fields, string $content): array {
    $scaleMap = [
        'none' => 'var(--section-space-none)',
        'xs' => 'var(--section-space-xs)',
        'sm' => 'var(--section-space-sm)',
        'md' => 'var(--section-space-md)',
        'lg' => 'var(--section-space-lg)',
        'xl' => 'var(--section-space-xl)',
    ];

    $resolve = static function (string $modeKey, string $scaleKey, string $customKey, string $defaultScale) use ($fields, $scaleMap): string {
        $mode = is_string($fields[$modeKey] ?? null) ? $fields[$modeKey] : 'scale';
        $custom = (int) ($fields[$customKey] ?? 0);
        if ($mode === 'custom' && $custom > 0) {
            return $custom . 'px';
        }

        $scale = is_string($fields[$scaleKey] ?? null) ? $fields[$scaleKey] : $defaultScale;

        return $scaleMap[$scale] ?? $scaleMap[$defaultScale];
    };

    $desktop = $resolve('spacer_desktop_mode', 'spacer_desktop_size', 'spacer_desktop_custom', 'md');
    $mobile = $resolve('spacer_mobile_mode', 'spacer_mobile_size', 'spacer_mobile_custom', 'sm');

    return [
        'spacer_style' => sprintf('--spacer-height-desktop: %1$s; --spacer-height-mobile: %2$s;', $desktop, $mobile),
        'spacer_label' => sprintf('Desktop: %s / Mobile: %s', $desktop, $mobile),
    ];
};
