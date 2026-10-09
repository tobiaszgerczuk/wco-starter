<?php

namespace WCO\Starter\Blocks;

use Timber\Timber;

/**
 * Builds the Twig context shared by every ACF block render template.
 */
class BlockContext
{
    /** Defaults for the shared "Section settings" group, used when a block was inserted from a pattern. */
    private const SECTION_DEFAULTS = [
        'section_gap_top' => 'none',
        'section_gap_bottom' => 'none',
        'section_space_top' => 'md',
        'section_space_bottom' => 'md',
        'container_width' => 'default',
    ];

    /**
     * @param array<string, mixed> $block   ACF block settings and attributes.
     * @param array<string, mixed> $extra   Extra context values (e.g. inner_template).
     * @return array<string, mixed>
     */
    public static function build(string $slug, array $block, string $content = '', bool $isPreview = false, array $extra = []): array
    {
        $fields = array_replace(self::SECTION_DEFAULTS, array_filter(get_fields() ?: [], static fn ($v) => $v !== null && $v !== ''));

        $context = Timber::context();
        $context['fields'] = $fields;
        $context['block'] = $block;
        $context['is_preview'] = $isPreview;
        $context['content'] = $content;
        $context['section_classes'] = SectionSettings::build_classes(
            $fields,
            ['block-' . $slug, !empty($block['align']) ? 'align' . $block['align'] : '']
        );
        $context['section_id'] = SectionSettings::section_id($fields);
        $context['section_style'] = SectionSettings::inline_style($fields);
        $context['container_class'] = SectionSettings::container_class($fields);

        return array_replace($context, $extra);
    }

    /** Renders `views/blocks/<slug>/<slug>.twig`. Used by every <slug>.include.php. */
    public static function render(string $slug, array $block, string $content = '', bool $isPreview = false, array $extra = []): void
    {
        Timber::render("blocks/{$slug}/{$slug}.twig", self::build($slug, $block, $content, $isPreview, $extra));
    }

    /**
     * Default heading group for section blocks (eyebrow, title, lead). Spans all grid columns.
     *
     * @return array{0: string, 1: array, 2: array}
     */
    public static function head_template(string $title = 'Nagłówek sekcji', string $lead = 'Krótki opis sekcji, który możesz edytować bezpośrednio w podglądzie.', string $eyebrow = 'Nadtytuł', int $level = 2): array
    {
        return ['core/group', ['className' => 'is-span-all block-head'], [
            ['core/paragraph', ['className' => 'is-eyebrow', 'content' => $eyebrow]],
            ['core/heading', ['level' => $level, 'content' => $title]],
            ['core/paragraph', ['className' => 'is-lead', 'content' => $lead]],
        ]];
    }

    /** Default button row template. */
    public static function buttons_template(string $primary = 'Dowiedz się więcej', ?string $secondary = null): array
    {
        $buttons = [['core/button', ['text' => $primary]]];
        if ($secondary !== null) {
            $buttons[] = ['core/button', ['text' => $secondary, 'className' => 'is-style-outline']];
        }

        return ['core/buttons', [], $buttons];
    }
}
