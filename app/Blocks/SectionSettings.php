<?php

namespace WCO\Starter\Blocks;

class SectionSettings
{
    private const SCALE = ['none', 'xs', 'sm', 'md', 'lg', 'xl'];
    private const CONTAINER_WIDTHS = ['default', 'wide', 'medium', 'narrow', 'full'];

    public static function build_classes(array $fields, array $baseClasses = []): string
    {
        $classes = array_filter($baseClasses);
        $classes[] = 'section';

        if (!empty($fields['section_has_background'])) {
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
