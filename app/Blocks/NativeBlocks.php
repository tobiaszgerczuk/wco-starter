<?php

namespace WCO\Starter\Blocks;

/**
 * Blocks that do not need ACF: registered natively from their block.json ("wco": { "native": true }).
 * Settings are block attributes edited in the inspector, content lives in InnerBlocks.
 */
class NativeBlocks
{
    private const BLOCKS_DIR = '/views/blocks';

    /** Shared "Section settings" attributes; the same names the ACF group used, so Twig and CSS stay untouched. */
    private const SECTION_ATTRIBUTES = [
        'section_background_color' => ['type' => 'string', 'default' => ''],
        'container_width' => ['type' => 'string', 'default' => 'default'],
        'block_id' => ['type' => 'string', 'default' => ''],
        'section_gap_top' => ['type' => 'string', 'default' => 'none'],
        'section_gap_bottom' => ['type' => 'string', 'default' => 'none'],
        'section_space_top' => ['type' => 'string', 'default' => 'md'],
        'section_space_bottom' => ['type' => 'string', 'default' => 'md'],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $definitions = null;

    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        foreach (self::definitions() as $slug => $definition) {
            $style = Registry::register_block_style_handle($slug);

            register_block_type($definition['name'], array_filter([
                'api_version' => 2,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'keywords' => $definition['keywords'],
                'supports' => $definition['supports'],
                'attributes' => $definition['attributes'],
                'parent' => $definition['parent'],
                'style' => $style,
                'editor_style' => $style,
                'render_callback' => static fn (array $attributes = [], string $content = '', $block = null): string => self::render($slug, $attributes, $content),
            ], static fn ($value) => $value !== '' && $value !== null));
        }
    }

    public static function is_native(array $metadata): bool
    {
        return !empty($metadata['name']) && !empty($metadata['wco']['native']);
    }

    /**
     * Data handed to the editor script (assets/js/native-blocks.js).
     *
     * @return list<array<string, mixed>>
     */
    public static function editor_config(): array
    {
        $config = [];
        foreach (self::definitions() as $slug => $definition) {
            $config[] = [
                'slug' => $slug,
                'name' => $definition['name'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'keywords' => $definition['keywords'],
                'supports' => $definition['supports'],
                'attributes' => $definition['attributes'],
                'panel' => $definition['panel'],
                'controls' => $definition['controls'],
                'frame' => $definition['frame'],
                'sectionSettings' => $definition['sectionSettings'],
                'parent' => $definition['parent'],
                'template' => $definition['extras']['inner_template'] ?? [],
                'allowedBlocks' => $definition['extras']['inner_allowed'] ?? [],
            ];
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function render(string $slug, array $attributes, string $content): string
    {
        // Content saved while the block was still an ACF block keeps its values under "data".
        $legacy = is_array($attributes['data'] ?? null) ? $attributes['data'] : [];
        $fields = array_replace($legacy, array_filter($attributes, static fn ($value) => $value !== null && $value !== ''));
        unset($fields['data']);

        $definition = self::definitions()[$slug];
        $extra = $definition['extras'];
        if ($definition['context'] !== null) {
            $extra = array_replace($extra, (array) ($definition['context'])($fields, $content));
        }

        ob_start();
        BlockContext::render_fields($slug, $fields, $content, ['align' => $attributes['align'] ?? ''], $extra);

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [];
        $root = get_template_directory() . self::BLOCKS_DIR;

        foreach (glob($root . '/*/block.json') ?: [] as $file) {
            $metadata = json_decode((string) file_get_contents($file), true);
            if (!is_array($metadata) || !self::is_native($metadata)) {
                continue;
            }

            $dir = dirname($file);
            $slug = basename($dir);
            $sectionSettings = !empty($metadata['wco']['sectionSettings']);
            $templateFile = $dir . '/' . $slug . '.template.php';
            $contextFile = $dir . '/' . $slug . '.context.php';
            $supports = is_array($metadata['supports'] ?? null) ? $metadata['supports'] : [];
            $supports['align'] = $supports['align'] ?? ['full', 'wide'];

            self::$definitions[$slug] = [
                'name' => $metadata['name'],
                'title' => $metadata['title'] ?? ucfirst($slug),
                'description' => $metadata['description'] ?? '',
                'category' => $metadata['category'] ?? 'wco-blocks',
                'icon' => $metadata['icon'] ?? 'layout',
                'keywords' => $metadata['keywords'] ?? [],
                'supports' => $supports,
                'attributes' => array_replace(
                    $sectionSettings ? self::SECTION_ATTRIBUTES : [],
                    is_array($metadata['attributes'] ?? null) ? $metadata['attributes'] : [],
                    ['data' => ['type' => 'object', 'default' => []]]
                ),
                'panel' => $metadata['wco']['panel'] ?? __('Block settings', 'wco-starter'),
                'controls' => is_array($metadata['wco']['controls'] ?? null) ? $metadata['wco']['controls'] : [],
                'sectionSettings' => $sectionSettings,
                'frame' => is_array($metadata['wco']['frame'] ?? null) ? $metadata['wco']['frame'] : [],
                'parent' => is_array($metadata['parent'] ?? null) ? $metadata['parent'] : null,
                'extras' => is_file($templateFile) ? (array) include $templateFile : [],
                'context' => is_file($contextFile) ? include $contextFile : null,
            ];
        }

        return self::$definitions;
    }
}
