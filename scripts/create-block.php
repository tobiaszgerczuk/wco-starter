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

    $positional[] = $arg;
}

if (count($positional) < 1) {
    $usage = <<<TXT
Usage:
  php scripts/create-block.php block-slug ["Block Title"] [--icon=layout] [--category=wco-blocks] [--dry-run]

Examples:
  php scripts/create-block.php hero-banner "Hero Banner"
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
$acfJsonDir = $themeDir . '/acf-json';
$fieldPrefix = str_replace('-', '_', $slug);
$textDomain = extractStyleHeader($themeDir . '/style.css', 'Text Domain') ?? 'wco-starter';
$timestamp = (string) time();

$files = [
    $blockDir . '/block.json' => build_block_metadata_json($slug, $title, $icon, $category),
    $blockDir . '/' . $slug . '.twig' => build_twig_template($slug, $title, $fieldPrefix),
    $blockDir . '/_' . $slug . '.scss' => build_scss_template($slug),
    $blockDir . '/' . $slug . '.js' => build_javascript_template($slug),
    $blockDir . '/' . $slug . '.include.php' => build_include_template($slug),
    $acfJsonDir . '/group_block_' . $slug . '.json' => build_field_group_json($slug, $title, $fieldPrefix, $icon, $category, $textDomain, $timestamp),
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

function build_twig_template(string $slug, string $title, string $fieldPrefix): string
{
    $fallback = addslashes($title);

    return <<<TWIG
<section class="{{ section_classes }}">
  <div class="container">
    <div class="block-{$slug}__inner">
      <h2 class="block-{$slug}__title">{{ fields['{$fieldPrefix}_title'] ?: '{$fallback}' }}</h2>

      {% if fields['{$fieldPrefix}_content'] %}
        <div class="block-{$slug}__content">
          {{ fields['{$fieldPrefix}_content']|wpautop }}
        </div>
      {% elseif is_preview %}
        <p class="block-{$slug}__placeholder">Add fields for the {$slug} block in ACF.</p>
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
                'type' => 'textarea',
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
                'rows' => 4,
                'placeholder' => '',
                'new_lines' => 'wpautop',
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
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'key' => 'field_' . $fieldPrefix . '_section_has_background',
            'label' => 'Section background',
            'name' => 'section_has_background',
            'aria-label' => '',
            'type' => 'true_false',
            'instructions' => 'Adds the section background helper class.',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '50',
                'class' => '',
                'id' => '',
            ],
            'message' => 'Use .section-bg on this block',
            'default_value' => 0,
            'ui' => 1,
            'ui_on_text' => 'On',
            'ui_off_text' => 'Off',
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

function build_javascript_template(string $slug): string
{
    $className = studly_case($slug);

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
