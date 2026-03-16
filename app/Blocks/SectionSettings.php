<?php

namespace WCO\Starter\Blocks;

class SectionSettings
{
    private const SCALE = ['none', 'xs', 'sm', 'md', 'lg', 'xl'];
    private const CONTAINER_WIDTHS = ['default', 'wide', 'medium', 'narrow', 'full'];
    private const LEGACY_BACKGROUND_COLORS = [
        'surface' => 'var(--color-surface)',
        'surface-strong' => 'var(--color-surface-strong)',
        'background' => 'var(--color-bg)',
        'white' => 'var(--color-white)',
    ];

    public static function build_classes(array $fields, array $baseClasses = []): string
    {
        $classes = array_filter($baseClasses);
        $classes[] = 'section';

        if (self::background_color($fields) !== null) {
            $classes[] = 'section-bg';
        }

        $mapping = [
            'section_gap_top' => 'section-gap-top-',
            'section_gap_bottom' => 'section-gap-bottom-',
            'section_space_top' => 'section-space-top-',
            'section_space_bottom' => 'section-space-bottom-',
        ];

        foreach ($mapping as $fieldKey => $classPrefix) {
            $value = $fields[$fieldKey] ?? null;
            if (is_string($value) && in_array($value, self::SCALE, true)) {
                $classes[] = $classPrefix . $value;
            }
        }

        return implode(' ', array_unique($classes));
    }

    public static function background_color(array $fields): ?string
    {
        $color = $fields['section_background_color'] ?? null;

        if (is_string($color)) {
            $color = trim($color);
            if ($color !== '' && $color !== 'none' && preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color) === 1) {
                return $color;
            }

            if (isset(self::LEGACY_BACKGROUND_COLORS[$color])) {
                return self::LEGACY_BACKGROUND_COLORS[$color];
            }
        }

        if (!empty($fields['section_has_background'])) {
            return self::LEGACY_BACKGROUND_COLORS['surface'];
        }

        return null;
    }

    public static function inline_style(array $fields): string
    {
        $styles = [];
        $backgroundColor = self::background_color($fields);

        if ($backgroundColor !== null) {
            $styles[] = '--section-background-color: ' . $backgroundColor;
        }

        return implode('; ', $styles);
    }

    public static function section_id(array $fields): string
    {
        $rawId = $fields['block_id'] ?? '';
        if (!is_string($rawId) || trim($rawId) === '') {
            return '';
        }

        return sanitize_title($rawId);
    }

    public static function container_class(array $fields, string $default = 'default'): string
    {
        $containerWidth = is_string($fields['container_width'] ?? null) ? $fields['container_width'] : $default;

        if (!in_array($containerWidth, self::CONTAINER_WIDTHS, true)) {
            $containerWidth = $default;
        }

        return match ($containerWidth) {
            'wide' => 'container-wide',
            'medium' => 'container-medium',
            'narrow' => 'container-narrow',
            'full' => '',
            default => 'container',
        };
    }
}
