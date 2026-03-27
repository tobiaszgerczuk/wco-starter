#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

$dryRun = false;
$icon = 'layout';
$category = 'wco-blocks';
$preset = 'basic';
$positional = [];

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (str_starts_with($arg, '--icon=')) {
        $icon = substr($arg, 7);
        continue;
    }

    if (str_starts_with($arg, '--category=')) {
        $category = substr($arg, 11);
        continue;
    }

    if (str_starts_with($arg, '--preset=')) {
        $preset = substr($arg, 9);
        continue;
    }

    $positional[] = $arg;
}

$availablePresets = ['basic', 'slider'];
if (!in_array($preset, $availablePresets, true)) {
    fwrite(STDERR, "Unsupported preset: {$preset}. Available presets: " . implode(', ', $availablePresets) . "\n");
    exit(1);
}

if (count($positional) < 1) {
    $usage = <<<TXT
Usage:
  php scripts/create-block.php block-slug ["Block Title"] [--icon=layout] [--category=wco-blocks] [--preset=basic] [--dry-run]

Examples:
  php scripts/create-block.php hero-banner "Hero Banner"
  php scripts/create-block.php testimonials-slider "Testimonials Slider" --preset=slider
  npm run create-block -- hero-banner "Hero Banner"

TXT;

    fwrite(STDERR, $usage);
    exit(1);
}

$slug = $positional[0];
$title = $positional[1] ?? humanize_slug($slug);

if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    fwrite(STDERR, "Block slug must use lowercase letters, numbers and hyphens only.\n");
    exit(1);
}

$themeDir = realpath(__DIR__ . '/..');
if ($themeDir === false) {
    fwrite(STDERR, "Could not resolve theme directory.\n");
    exit(1);
}

$blockDir = $themeDir . '/views/blocks/' . $slug;
$blockAcfJsonPath = $blockDir . '/group_' . $slug . '.json';
$fieldPrefix = str_replace('-', '_', $slug);
$textDomain = extractStyleHeader($themeDir . '/style.css', 'Text Domain') ?? 'wco-starter';
$timestamp = (string) time();

$files = [
    $blockDir . '/block.json' => build_block_metadata_json($slug, $title, $icon, $category),
    $blockDir . '/' . $slug . '.twig' => build_twig_template($slug, $title, $fieldPrefix, $preset),
    $blockDir . '/_' . $slug . '.scss' => build_scss_template($slug),
    $blockDir . '/' . $slug . '.js' => build_javascript_template($slug, $preset),
    $blockDir . '/' . $slug . '.include.php' => build_include_template($slug),
    $blockAcfJsonPath => build_field_group_json($slug, $title, $fieldPrefix, $icon, $category, $textDomain, $timestamp),
];

if (is_dir($blockDir)) {
    fwrite(STDERR, "Block directory already exists: {$blockDir}\n");
    exit(1);
}

foreach (array_keys($files) as $filePath) {
    if (file_exists($filePath)) {
        fwrite(STDERR, "File already exists: {$filePath}\n");
        exit(1);
    }
}

echo $dryRun ? "Dry run complete.\n" : "Block scaffold created.\n";
echo "Block: {$slug}\n";
echo "Title: {$title}\n";
echo "Preset: {$preset}\n";
echo "Files:\n";

foreach ($files as $filePath => $contents) {
    echo ' - ' . str_replace($themeDir . '/', '', $filePath) . "\n";

    if ($dryRun) {
        continue;
    }

    $dir = dirname($filePath);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "Failed to create directory: {$dir}\n");
        exit(1);
    }

    if (file_put_contents($filePath, $contents) === false) {
        fwrite(STDERR, "Failed to write file: {$filePath}\n");
        exit(1);
    }
}

if (!$dryRun) {
    echo "Next: open ACF Field Groups to adjust fields if needed, then run npm run build.\n";
}

function build_twig_template(string $slug, string $title, string $fieldPrefix, string $preset): string
{
    if ($preset === 'slider') {
        return build_slider_twig_template($slug, $title, $fieldPrefix);
    }

    $fallback = addslashes($title);

    return <<<TWIG
<section{% if section_id %} id="{{ section_id }}"{% endif %} class="{{ section_classes }}"{% if section_style %} style="{{ section_style }}"{% endif %}>
  <div class="container">
    <div class="block-{$slug}__inner">
      <h2 class="block-{$slug}__title">{{ fields['{$fieldPrefix}_title'] ?: '{$fallback}' }}</h2>

      {% if fields['{$fieldPrefix}_content'] %}
        <div class="block-{$slug}__content">
          {{ fields['{$fieldPrefix}_content']|raw }}
        </div>
      {% elseif is_preview %}
        <p class="block-{$slug}__placeholder">Add fields for the {$slug} block in ACF.</p>
      {% endif %}
    </div>
  </div>
</section>

TWIG;
}

function build_slider_twig_template(string $slug, string $title, string $fieldPrefix): string
{
    $fallback = addslashes($title);

    return <<<TWIG
<section{% if section_id %} id="{{ section_id }}"{% endif %} class="{{ section_classes }}"{% if section_style %} style="{{ section_style }}"{% endif %}>
  <div class="container">
    <div class="block-{$slug}__inner">
      <h2 class="block-{$slug}__title">{{ fields['{$fieldPrefix}_title'] ?: '{$fallback}' }}</h2>

      <div class="swiper js-swiper-{$slug}">
        <div class="swiper-wrapper">
          {% if is_preview %}
            {% for i in 1..3 %}
              <div class="swiper-slide block-{$slug}__slide">
                <p>Slide {{ i }}</p>
              </div>
            {% endfor %}
          {% endif %}
        </div>
        <div class="swiper-pagination"></div>
      </div>

      {% if is_preview and not fields['{$fieldPrefix}_content'] %}
        <p class="block-{$slug}__placeholder">Set up slides and swiper options in this block.</p>
      {% endif %}
    </div>
  </div>
</section>

TWIG;
}

function build_scss_template(string $slug): string
{
    $template = <<<'SCSS'
.block-__SLUG__ {
  padding: 4rem 0;

  &__inner {
    @include container;
  }

  &__title {
    margin: 0 0 1rem;
  }

  &__content {
    max-width: 60ch;
  }

  &__placeholder {
    color: $color-muted;
  }
}

SCSS;

    return str_replace('__SLUG__', $slug, $template);
}

function build_include_template(string $slug): string
{
    return <<<PHP
<?php

use Timber\Timber;
use WCO\Starter\Blocks\SectionSettings;

\$context = Timber::context();
\$context['fields'] = get_fields() ?: [];
\$context['block'] = \$block ?? [];
\$context['is_preview'] = \$is_preview ?? false;
\$context['post_id'] = \$post_id ?? 0;
\$context['section_classes'] = SectionSettings::build_classes(
    \$context['fields'],
    ['block-{$slug}', !empty(\$context['block']['align']) ? 'align' . \$context['block']['align'] : '']
);
\$context['section_id'] = SectionSettings::section_id(\$context['fields']);
\$context['section_style'] = SectionSettings::inline_style(\$context['fields']);

Timber::render('blocks/{$slug}/{$slug}.twig', \$context);

PHP;
}

function build_block_metadata_json(string $slug, string $title, string $icon, string $category): string
{
    $metadata = [
        'title' => $title,
        'description' => sprintf('Block: %s', $title),
        'category' => $category,
        'icon' => $icon,
        'keywords' => [$slug],
    ];

    return json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function build_field_group_json(string $slug, string $title, string $fieldPrefix, string $icon, string $category, string $textDomain, string $timestamp): string
{
    $group = [
        'key' => 'group_block_' . $slug,
        'title' => $title . ' Block',
        'fields' => array_merge([
            [
                'key' => 'field_' . $fieldPrefix . '_title',
                'label' => 'Title',
                'name' => $fieldPrefix . '_title',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'maxlength' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_' . $fieldPrefix . '_content',
                'label' => 'Content',
                'name' => $fieldPrefix . '_content',
                'aria-label' => '',
                'type' => 'wysiwyg',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 1,
                'delay' => 0,
                'placeholder' => '',
            ],
        ], build_section_fields($fieldPrefix)),
        'location' => [
            [
                [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/' . $slug,
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => sprintf('Generated for the %s block.', $title),
        'show_in_rest' => 0,
        'modified' => (int) $timestamp,
        'wco_metadata' => [
            'block_name' => $slug,
            'icon' => $icon,
            'category' => $category,
            'text_domain' => $textDomain,
        ],
    ];

    return json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function build_section_fields(string $fieldPrefix): array
{
    $choices = [
        'none' => 'None',
        'xs' => 'XS',
        'sm' => 'SM',
        'md' => 'MD',
        'lg' => 'LG',
        'xl' => 'XL',
    ];

    return [
        [
            'key' => 'field_' . $fieldPrefix . '_section_tab',
            'label' => 'Section settings',
            'name' => '',
            'aria-label' => '',
            'type' => 'accordion',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'open' => 0,
            'multi_expand' => 1,
            'endpoint' => 0,
        ],
        [
            'key' => 'field_' . $fieldPrefix . '_section_background_color',
            'label' => 'Background color',
            'name' => 'section_background_color',
            'aria-label' => '',
            'type' => 'color_picker',
            'instructions' => 'Select a background color for this section.',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '50',
                'class' => '',
                'id' => '',
            ],
            'default_value' => '',
            'enable_opacity' => 0,
            'return_format' => 'string',
        ],
        [
            'key' => 'field_' . $fieldPrefix . '_block_id',
            'label' => 'Block ID',
            'name' => 'block_id',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => 'Optional HTML id attribute for anchor links, e.g. contact-section.',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '50',
                'class' => '',
                'id' => '',
            ],
            'default_value' => '',
            'maxlength' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
        ],
        [
            'key' => 'field_' . $fieldPrefix . '_spacing_tab',
            'label' => 'Spacing settings',
            'name' => '',
            'aria-label' => '',
            'type' => 'accordion',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'open' => 0,
            'multi_expand' => 1,
            'endpoint' => 0,
        ],
        build_spacing_select($fieldPrefix, 'section_gap_top', 'Gap top', '50', 'none', $choices),
        build_spacing_select($fieldPrefix, 'section_gap_bottom', 'Gap bottom', '50', 'none', $choices),
        build_spacing_select($fieldPrefix, 'section_space_top', 'Space top', '50', 'md', $choices),
        build_spacing_select($fieldPrefix, 'section_space_bottom', 'Space bottom', '50', 'md', $choices),
    ];
}

function build_spacing_select(string $fieldPrefix, string $name, string $label, string $width, string $defaultValue, array $choices): array
{
    return [
        'key' => 'field_' . $fieldPrefix . '_' . $name,
        'label' => $label,
        'name' => $name,
        'aria-label' => '',
        'type' => 'select',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => [
            'width' => $width,
            'class' => '',
            'id' => '',
        ],
        'choices' => $choices,
        'default_value' => $defaultValue,
        'return_format' => 'value',
        'multiple' => 0,
        'allow_null' => 0,
        'ui' => 0,
        'ajax' => 0,
        'placeholder' => '',
    ];
}

function build_javascript_template(string $slug, string $preset): string
{
    $className = studly_case($slug);
    if ($preset === 'slider') {
        return build_slider_javascript_template($slug, $className);
    }

    return <<<JS
export default class {$className} {
  static selector = '.block-{$slug}';

  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    // Block-specific behavior goes here.
  }
}

JS;
}

function build_slider_javascript_template(string $slug, string $className): string
{
    return <<<JS
import swipers from '../../../assets/js/modules/swipers.js';

export default class {$className} {
  static selector = '.block-{$slug}';
  static registered = false;

  constructor(element) {
    this.element = element;
    this.init();
  }

  init() {
    if ({$className}.registered) {
      return;
    }

    swipers.register({
      name: '{$slug}',
      selector: '.js-swiper-{$slug}',
      options: (element) => ({
        slidesPerView: 1,
        spaceBetween: 24,
        pagination: {
          el: element.querySelector('.swiper-pagination'),
          clickable: true,
        },
      }),
    });

    {$className}.registered = true;
  }
}

JS;
}

function extractStyleHeader(string $stylePath, string $header): ?string
{
    $contents = @file_get_contents($stylePath);
    if ($contents === false) {
        return null;
    }

    $pattern = '/^' . preg_quote($header, '/') . ':\s*(.+)$/mi';
    if (!preg_match($pattern, $contents, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

function humanize_slug(string $slug): string
{
    $parts = preg_split('/-+/', $slug) ?: [];
    $parts = array_map(
        static fn(string $part): string => ucfirst($part),
        array_filter($parts, static fn(string $part): bool => $part !== '')
    );

    return implode(' ', $parts);
}

function studly_case(string $slug): string
{
    $parts = preg_split('/-+/', $slug) ?: [];
    $parts = array_map(
        static fn(string $part): string => ucfirst($part),
        array_filter($parts, static fn(string $part): bool => $part !== '')
    );

    return implode('', $parts);
}
