<?php

namespace WCO\Starter\Core;

/**
 * "Appearance" tabs of the Theme settings page: colours, fonts, logo, header behaviour and shape.
 *
 * Values are printed as CSS custom properties on :root (front end and block editor), which the
 * theme stylesheets already read (var(--color-primary), var(--font-family-base), ...).
 * Fonts are served from uploads/wco-fonts (downloaded once when settings are saved) unless the
 * "host fonts locally" switch is off, in which case Google Fonts is linked.
 */
class Appearance
{
    private const PREFIX = 'appearance_';
    private const FONT_OPTION = 'wco_appearance_fonts';

    /** Same values as assets/scss/base/_variables.scss. */
    private const DEFAULTS = [
        'color_primary' => '#5ab25e',
        'color_primary_hover' => '',
        'color_secondary' => '#7a5937',
        'color_accent' => '#cdae7f',
        'color_text' => '#191919',
        'color_muted' => '#666666',
        'color_bg' => '#ffffff',
        'color_surface' => '#f7f3eb',
        'color_border' => '#d9cfbf',
        'font_heading' => 'montserrat',
        'font_body' => 'montserrat',
        'font_size' => 16,
        'font_local' => true,
        'logo_height' => 40,
        'header_mode' => 'fixed',
        'header_transparent' => true,
        'header_shrink' => true,
        'shape' => 'default',
    ];

    /** key => [label, Google Fonts family spec, serif?]. A null spec means a system stack. */
    private const FONTS = [
        'montserrat' => ['Montserrat', 'Montserrat:ital,wght@0,100..900;1,100..900', false],
        'inter' => ['Inter', 'Inter:wght@100..900', false],
        'poppins' => ['Poppins', 'Poppins:wght@400;500;600;700', false],
        'roboto' => ['Roboto', 'Roboto:ital,wght@0,100..900;1,100..900', false],
        'open-sans' => ['Open Sans', 'Open+Sans:ital,wght@0,300..800;1,300..800', false],
        'lato' => ['Lato', 'Lato:wght@400;700', false],
        'dm-sans' => ['DM Sans', 'DM+Sans:wght@100..1000', false],
        'nunito' => ['Nunito', 'Nunito:ital,wght@0,200..1000;1,200..1000', false],
        'raleway' => ['Raleway', 'Raleway:ital,wght@0,100..900;1,100..900', false],
        'work-sans' => ['Work Sans', 'Work+Sans:wght@100..900', false],
        'space-grotesk' => ['Space Grotesk', 'Space+Grotesk:wght@300..700', false],
        'playfair' => ['Playfair Display', 'Playfair+Display:ital,wght@0,400..900;1,400..900', true],
        'merriweather' => ['Merriweather', 'Merriweather:ital,wght@0,300..900;1,300..900', true],
        'system' => ['System font', null, false],
    ];

    /** key => [sm, md, lg] border radius in px. */
    private const SHAPES = [
        'sharp' => [0, 0, 0],
        'soft' => [3, 5, 8],
        'default' => [6, 10, 16],
        'round' => [10, 16, 28],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $settings = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /** @return array<string, string> font key => label */
    public static function font_labels(): array
    {
        return array_map(static fn (array $font): string => $font[0], self::FONTS);
    }

    public static function boot(): void
    {
        add_action('wco_starter_settings_saved', [self::class, 'on_save'], 20);
        add_action('wp_head', [self::class, 'print_head'], 4);
        add_action('wp_head', [self::class, 'print_variables'], 20);
        add_action('enqueue_block_editor_assets', [self::class, 'editor_assets'], 20);
        add_filter('body_class', [self::class, 'body_classes']);
        add_filter('timber/context', [self::class, 'add_to_context']);
    }

    // ------------------------------------------------------------------ settings

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $settings = self::DEFAULTS;
        foreach (self::DEFAULTS as $name => $default) {
            $value = Settings::get(self::PREFIX . $name);
            if ($value === null || $value === '') {
                continue;
            }
            $settings[$name] = is_bool($default) ? (bool) $value : $value;
        }
        $settings['logo_id'] = (int) Settings::get(self::PREFIX . 'logo', 0);

        foreach (['color_primary', 'color_primary_hover', 'color_secondary', 'color_accent', 'color_text', 'color_muted', 'color_bg', 'color_surface', 'color_border'] as $name) {
            $settings[$name] = self::color((string) $settings[$name], (string) self::DEFAULTS[$name]);
        }
        foreach (['font_heading', 'font_body'] as $name) {
            $settings[$name] = isset(self::FONTS[$settings[$name]]) ? $settings[$name] : 'montserrat';
        }
        $settings['font_size'] = max(14, min(20, (int) $settings['font_size']));
        $settings['logo_height'] = max(16, min(160, (int) $settings['logo_height']));
        $settings['header_mode'] = $settings['header_mode'] === 'static' ? 'static' : 'fixed';
        $settings['shape'] = isset(self::SHAPES[$settings['shape']]) ? $settings['shape'] : 'default';
        $settings['logo_id'] = (int) ($settings['logo_id'] ?? 0);

        return self::$settings = $settings;
    }

    private static function color(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value) === 1 ? strtolower($value) : $fallback;
    }

    /** Darkens a #rrggbb colour; used for the primary hover when none is chosen. */
    private static function darken(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $channels = array_map(static fn (string $c): int => (int) round(hexdec($c) * 0.82), str_split($hex, 2));

        return sprintf('#%02x%02x%02x', ...$channels);
    }

    // ------------------------------------------------------------------ CSS output

    public static function css(bool $editor = false): string
    {
        $s = self::settings();
        $stack = static fn (string $key): string => self::font_stack($key);
        [$sm, $md, $lg] = self::SHAPES[$s['shape']];

        $vars = [
            '--color-primary' => $s['color_primary'],
            '--color-primary-hover' => $s['color_primary_hover'] !== '' ? $s['color_primary_hover'] : self::darken($s['color_primary']),
            '--color-secondary' => $s['color_secondary'],
            '--color-accent' => $s['color_accent'],
            '--color-text' => $s['color_text'],
            '--color-muted' => $s['color_muted'],
            '--color-bg' => $s['color_bg'],
            '--color-surface' => $s['color_surface'],
            '--color-border' => $s['color_border'],
            '--font-family-base' => $stack($s['font_body']),
            '--font-family-heading' => $stack($s['font_heading']),
            '--font-size-base' => $s['font_size'] . 'px',
            '--logo-height' => $s['logo_height'] . 'px',
            '--radius-sm' => $sm . 'px',
            '--radius-md' => $md . 'px',
            '--radius-lg' => $lg . 'px',
        ];

        // WordPress presets generated from theme.json (used by the block editor and .has-*-color classes).
        $presets = [
            'text' => '--color-text', 'bg' => '--color-bg', 'surface' => '--color-surface', 'border' => '--color-border',
            'muted' => '--color-muted', 'primary' => '--color-primary', 'primary-hover' => '--color-primary-hover',
            'secondary' => '--color-secondary', 'accent' => '--color-accent',
        ];
        foreach ($presets as $slug => $source) {
            $vars['--wp--preset--color--' . $slug] = $vars[$source];
        }
        $vars['--wp--preset--font-family--base'] = $vars['--font-family-base'];
        $vars['--wp--preset--font-family--heading'] = $vars['--font-family-heading'];

        $declarations = '';
        foreach ($vars as $name => $value) {
            $declarations .= $name . ':' . $value . ';';
        }

        $selector = $editor
            ? '.editor-styles-wrapper,.editor-styles-wrapper [data-type^="acf/"]'
            : ':root,.editor-styles-wrapper,.editor-styles-wrapper [data-type^="acf/"]';

        return $selector . '{' . $declarations . '}';
    }

    public static function print_variables(): void
    {
        echo '<style id="wco-appearance">' . self::css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- values are validated in settings().
    }

    // ------------------------------------------------------------------ fonts

    private static function font_stack(string $key): string
    {
        [$label, $spec, $serif] = self::FONTS[$key];
        $fallback = $serif
            ? "Georgia, 'Times New Roman', serif"
            : "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

        return $spec === null ? $fallback : "'" . $label . "', " . $fallback;
    }

    /** @return string[] Google Fonts family specs needed by the current settings. */
    private static function family_specs(): array
    {
        $s = self::settings();
        $specs = [];
        foreach ([$s['font_heading'], $s['font_body']] as $key) {
            $spec = self::FONTS[$key][1];
            if ($spec !== null) {
                $specs[$spec] = $spec;
            }
        }

        return array_values($specs);
    }

    private static function fonts_hash(): string
    {
        return md5(implode('|', self::family_specs()) . '|local');
    }

    /** Prints either locally hosted @font-face rules or a Google Fonts stylesheet link. */
    public static function print_head(): void
    {
        $specs = self::family_specs();
        if (!$specs) {
            return;
        }

        $css = self::settings()['font_local'] ? self::local_css() : null;
        if ($css !== null) {
            echo '<style id="wco-fonts">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- generated from validated downloads.
            return;
        }

        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="stylesheet" href="' . esc_url(self::google_url($specs)) . '">' . "\n";
    }

    /** @param string[] $specs */
    private static function google_url(array $specs): string
    {
        return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $specs) . '&display=swap';
    }

    /** Cached local CSS for the current font choice, generated on demand. */
    private static function local_css(): ?string
    {
        $cached = get_option(self::FONT_OPTION);
        if (is_array($cached) && ($cached['hash'] ?? '') === self::fonts_hash() && !empty($cached['css'])) {
            return (string) $cached['css'];
        }

        return self::build_local_fonts();
    }

    /**
     * Downloads the latin and latin-ext woff2 files (Polish needs latin-ext) into uploads/wco-fonts
     * and stores the rewritten @font-face CSS. Returns null when anything fails, so callers fall
     * back to the Google Fonts link.
     */
    private static function build_local_fonts(): ?string
    {
        $specs = self::family_specs();
        if (!$specs) {
            return null;
        }

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'] . '/wco-fonts';
        $url = $uploads['baseurl'] . '/wco-fonts';
        if (!wp_mkdir_p($dir)) {
            return null;
        }

        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
        $css = '';

        foreach ($specs as $spec) {
            $response = wp_remote_get(self::google_url([$spec]), ['timeout' => 15, 'user-agent' => $agent]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }

            preg_match_all('#/\*\s*([a-z0-9-]+)\s*\*/\s*(@font-face\s*\{[^}]*\})#i', (string) wp_remote_retrieve_body($response), $blocks, PREG_SET_ORDER);

            foreach ($blocks as [, $subset, $block]) {
                if (!in_array($subset, ['latin', 'latin-ext'], true)) {
                    continue;
                }
                if (preg_match('#url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)#', $block, $source) !== 1) {
                    continue;
                }

                $file = substr(md5($source[1]), 0, 16) . '.woff2';
                if (!file_exists($dir . '/' . $file)) {
                    $download = wp_remote_get($source[1], ['timeout' => 20, 'user-agent' => $agent]);
                    if (is_wp_error($download) || wp_remote_retrieve_response_code($download) !== 200) {
                        return null;
                    }
                    file_put_contents($dir . '/' . $file, wp_remote_retrieve_body($download));
                }

                $css .= str_replace($source[1], $url . '/' . $file, $block) . "\n";
            }
        }

        if ($css === '') {
            return null;
        }

        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        update_option(self::FONT_OPTION, ['hash' => self::fonts_hash(), 'css' => $css], false);

        return $css;
    }

    /** Rebuilds the font cache right after the settings are saved. */
    public static function on_save(): void
    {
        self::$settings = null;
        delete_option(self::FONT_OPTION);
        if (self::settings()['font_local']) {
            self::build_local_fonts();
        }
    }

    // ------------------------------------------------------------------ editor, body classes, context

    public static function editor_assets(): void
    {
        if (!wp_style_is('wco-blocks-editor-base', 'enqueued')) {
            return;
        }

        $css = self::css(true);
        $fonts = self::settings()['font_local'] ? self::local_css() : null;
        if ($fonts !== null) {
            $css = $fonts . $css;
        } elseif (self::family_specs()) {
            $css = '@import url("' . esc_url_raw(self::google_url(self::family_specs())) . '");' . $css;
        }

        wp_add_inline_style('wco-blocks-editor-base', $css);
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public static function body_classes(array $classes): array
    {
        $s = self::settings();
        if ($s['header_mode'] === 'static') {
            $classes[] = 'header-static';
        }
        if (!$s['header_transparent']) {
            $classes[] = 'header-solid';
        }
        if (!$s['header_shrink']) {
            $classes[] = 'header-no-shrink';
        }

        return $classes;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function add_to_context(array $context): array
    {
        $context['appearance'] = ['logo_id' => self::settings()['logo_id']];

        return $context;
    }
}
