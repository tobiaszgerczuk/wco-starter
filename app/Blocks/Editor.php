<?php

namespace WCO\Starter\Blocks;

/**
 * Editor-side helpers: native block styles used inside theme blocks and pattern categories.
 */
class Editor
{
    public static function boot(): void
    {
        add_action('init', [self::class, 'register_block_styles']);
        add_action('init', [self::class, 'register_pattern_category']);
    }

    /** Button variants for core/button; styled in assets/scss/components/_buttons.scss. */
    public static function register_block_styles(): void
    {
        $variants = [
            'secondary' => __('Secondary', 'wco-starter'),
            'outline' => __('Outline', 'wco-starter'),
            'link' => __('Link', 'wco-starter'),
        ];

        foreach ($variants as $name => $label) {
            register_block_style('core/button', ['name' => $name, 'label' => $label]);
        }
    }

    public static function register_pattern_category(): void
    {
        register_block_pattern_category('wco-sections', [
            'label' => __('WCO sections', 'wco-starter'),
        ]);
    }
}
