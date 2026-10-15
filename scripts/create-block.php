#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scaffolds a native theme block (no ACF): views/blocks/<slug>/{block.json, <slug>.twig,
 * _<slug>.scss, <slug>.template.php}. Settings are attributes edited in the inspector (see
 * wco.controls in block.json), content is InnerBlocks edited in the preview.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

$dryRun = false;
$icon = 'layout';
$category = 'wco-blocks';
$preset = 'content';
$positional = [];

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--icon=')) {
        $icon = substr($arg, 7);
    } elseif (str_starts_with($arg, '--category=')) {
        $category = substr($arg, 11);
    } elseif (str_starts_with($arg, '--preset=')) {
        $preset = substr($arg, 9);
    } else {
        $positional[] = $arg;
    }
}

if ($preset === 'basic') {
    $preset = 'content';
}

if (!in_array($preset, ['content', 'items'], true)) {
    fwrite(STDERR, "Unsupported preset: {$preset}. Available presets: content, items\n");
    exit(1);
}

if (count($positional) < 1) {
    fwrite(STDERR, <<<TXT
Usage:
  php scripts/create-block.php block-slug ["Block Title"] [--icon=layout] [--category=wco-blocks] [--preset=content|items] [--dry-run]

Examples:
  php scripts/create-block.php promo "Promo"                      # section with text/images edited in the preview
  php scripts/create-block.php team "Team" --preset=items         # parent block + team-item child blocks
  npm run create-block -- promo "Promo"

TXT);
    exit(1);
}

$slug = $positional[0];
$title = $positional[1] ?? ucwords(str_replace('-', ' ', $slug));

if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    fwrite(STDERR, "Block slug must use lowercase letters, numbers and hyphens only.\n");
    exit(1);
}

$themeDir = realpath(__DIR__ . '/..');
if ($themeDir === false) {
    fwrite(STDERR, "Could not resolve theme directory.\n");
    exit(1);
}

$isItems = $preset === 'items';
$child = $slug . '-item';
$prefix = str_replace('-', '_', $slug);
$json = static fn (array $data): string => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

$parentJson = [
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 2,
    'name' => 'acf/' . $slug,
    'title' => $title,
    'description' => 'Block: ' . $title,
    'category' => $category,
    'icon' => $icon,
    'keywords' => [$slug],
    'supports' => ['align' => ['full', 'wide']],
    'attributes' => $isItems ? [$prefix . '_columns' => ['type' => 'string', 'default' => '3']] : (object) [],
    'wco' => array_filter([
        'native' => true,
        'sectionSettings' => true,
        'panel' => 'Ustawienia: ' . $title,
        'controls' => $isItems ? [[
            'attr' => $prefix . '_columns',
            'type' => 'select',
            'label' => 'Kolumny',
            'options' => ['2' => '2 kolumny', '3' => '3 kolumny', '4' => '4 kolumny'],
        ]] : null,
        'frame' => ['inner' => $isItems
            ? "block-{$slug}__items block-{$slug}__items--cols-{{$prefix}_columns:3}"
            : "block-{$slug}__inner"],
    ]),
];

$childJson = [
    '$schema' => 'https://schemas.wp.org/trunk/block.json',
    'apiVersion' => 2,
    'name' => 'acf/' . $child,
    'title' => $title . ' item',
    'description' => 'Item of the ' . $title . ' block.',
    'category' => $category,
    'icon' => $icon,
    'keywords' => [$child],
    'parent' => ['acf/' . $slug],
    'supports' => (object) [],
    'wco' => ['native' => true, 'frame' => ['item' => ['tag' => 'article', 'class' => "block-{$child}"]]],
];

$sectionTwig = static fn (string $inner, string $innerClass): string => <<<TWIG
<section{% if section_id %} id="{{ section_id }}"{% endif %} class="{{ section_classes }}"{% if section_style %} style="{{ section_style }}"{% endif %}>
  <div{% if container_class %} class="{{ container_class }}"{% endif %}>
    <div class="{$innerClass}">
      {$inner}
    </div>
  </div>
</section>

TWIG;

$contentTemplate = <<<'PHPT'
[
        ['core/paragraph', ['className' => 'is-eyebrow', 'content' => 'Nadtytuł']],
        ['core/heading', ['level' => 2, 'content' => 'Nagłówek sekcji']],
        ['core/paragraph', ['content' => 'Tutaj wpisz treść. Edytujesz ją bezpośrednio w podglądzie.']],
        BlockContext::buttons_template(),
    ]
PHPT;

$parentTemplate = $isItems
    ? "<?php\n\nuse WCO\\Starter\\Blocks\\BlockContext;\n\nreturn [\n    'inner_template' => [\n        BlockContext::head_template('{$title}'),\n        ...array_fill(0, 3, ['acf/{$child}', []]),\n    ],\n    'inner_allowed' => ['core/group', 'core/heading', 'core/paragraph', 'acf/{$child}'],\n];\n"
    : "<?php\n\nuse WCO\\Starter\\Blocks\\BlockContext;\n\nreturn [\n    'inner_template' => {$contentTemplate},\n];\n";

$scss = $isItems
    ? <<<SCSS
.block-{$slug} {
  &__items {
    @include inner-layout {
      @include grid-columns(3);

      > * { margin-block: 0; }
    }
  }
}

.block-{$child} {
  height: 100%;
  padding: 2rem;
  border-radius: \$radius-lg;
  background: var(--color-surface);
}

SCSS
    : <<<SCSS
.block-{$slug} {
  &__inner {
    max-width: 60ch;
  }
}

SCSS;

$dir = $themeDir . '/views/blocks/' . $slug;
$files = [
    $dir . '/block.json' => $json($parentJson),
    $dir . '/' . $slug . '.twig' => $isItems
        ? $sectionTwig('{{ inner_blocks(inner_template, inner_allowed) }}', "block-{$slug}__items block-{$slug}__items--cols-{{ fields.{$prefix}_columns|default(3) }}")
        : $sectionTwig('{{ inner_blocks(inner_template) }}', "block-{$slug}__inner"),
    $dir . '/_' . $slug . '.scss' => $scss,
    $dir . '/' . $slug . '.template.php' => $parentTemplate,
];

if ($isItems) {
    $childDir = $themeDir . '/views/blocks/' . $child;
    $files[$childDir . '/block.json'] = $json($childJson);
    $files[$childDir . '/' . $child . '.twig'] = "<article class=\"block-{$child}\">\n  {{ inner_blocks(inner_template) }}\n</article>\n";
    $files[$childDir . '/' . $child . '.template.php'] = "<?php\n\nreturn [\n    'inner_template' => [\n        ['core/heading', ['level' => 3, 'content' => 'Tytuł elementu']],\n        ['core/paragraph', ['content' => 'Krótki opis elementu.']],\n    ],\n];\n";
}

if (is_dir($dir) || ($isItems && is_dir($themeDir . '/views/blocks/' . $child))) {
    fwrite(STDERR, "Block directory already exists: {$dir}\n");
    exit(1);
}

echo $dryRun ? "Dry run complete.\n" : "Block scaffold created.\n";
echo "Block: {$slug}\nTitle: {$title}\nPreset: {$preset}\nFiles:\n";

foreach ($files as $filePath => $contents) {
    echo ' - ' . str_replace($themeDir . '/', '', $filePath) . "\n";

    if ($dryRun) {
        continue;
    }

    $fileDir = dirname($filePath);
    if (!is_dir($fileDir) && !mkdir($fileDir, 0777, true) && !is_dir($fileDir)) {
        fwrite(STDERR, "Failed to create directory: {$fileDir}\n");
        exit(1);
    }

    if (file_put_contents($filePath, $contents) === false) {
        fwrite(STDERR, "Failed to write file: {$filePath}\n");
        exit(1);
    }
}

if (!$dryRun) {
    echo "Next: run npm run build, then insert the block in the editor. Default content: {$slug}.template.php; settings: wco.controls in block.json.\n";
}
