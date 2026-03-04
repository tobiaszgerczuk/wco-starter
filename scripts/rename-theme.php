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
$renameDirectory = true;
$positional = [];

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if ($arg === '--no-rename-dir') {
        $renameDirectory = false;
        continue;
    }

    $positional[] = $arg;
}

if (count($positional) < 2) {
    $usage = <<<TXT
Usage:
  php scripts/rename-theme.php "Project Name" project-slug [--dry-run] [--no-rename-dir]

Examples:
  php scripts/rename-theme.php "Acme Website" acme-website
  npm run rename-theme -- "Acme Website" acme-website

TXT;

    fwrite(STDERR, $usage);
    exit(1);
}

[$projectName, $projectSlug] = $positional;

if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $projectSlug)) {
    fwrite(STDERR, "Slug must use lowercase letters, numbers and hyphens only.\n");
    exit(1);
}

$themeDir = realpath(__DIR__ . '/..');
if ($themeDir === false) {
    fwrite(STDERR, "Could not resolve theme directory.\n");
    exit(1);
}

$currentSlug = basename($themeDir);
$currentThemeName = extractThemeName($themeDir . '/style.css') ?? 'WCO Starter';
$currentTextDomain = extractStyleHeader($themeDir . '/style.css', 'Text Domain') ?? 'wco-starter';
$currentRestNamespace = extractRestNamespace($themeDir . '/app/Rest/Api.php') ?? $currentTextDomain;
$currentComposerName = extractComposerName($themeDir . '/composer.json') ?? 'wco/wp-starter';
$currentPackageName = extractPackageName($themeDir . '/package.json') ?? $currentTextDomain;
$currentPhpNamespace = extractPhpNamespace($themeDir . '/app/Core/Theme.php') ?? 'WCO\\Starter';

$newThemeName = trim($projectName);
$newTextDomain = $projectSlug;
$newPackageName = $projectSlug;
$newComposerName = buildComposerPackageName($projectSlug);
$newPhpNamespace = buildPhpNamespace($projectSlug);
$newJsNamespace = buildJsNamespace($projectSlug);
$currentThemeUri = extractThemeUri($themeDir . '/style.css') ?? "https://example.com/{$currentTextDomain}";
$newThemeUri = "https://example.com/{$newTextDomain}";

$targetThemeDir = dirname($themeDir) . '/' . $projectSlug;
$shouldRenameDirectory = $renameDirectory && $currentSlug !== $projectSlug;

if ($shouldRenameDirectory && is_dir($targetThemeDir)) {
    fwrite(STDERR, "Target directory already exists: {$targetThemeDir}\n");
    exit(1);
}

$replacements = [
    $currentThemeName => $newThemeName,
    $currentTextDomain => $newTextDomain,
    $currentComposerName => $newComposerName,
    $currentPackageName => $newPackageName,
    $currentPhpNamespace => $newPhpNamespace,
    addcslashes($currentPhpNamespace, '\\') => addcslashes($newPhpNamespace, '\\'),
    str_replace('-', '_', $currentTextDomain) => str_replace('-', '_', $newTextDomain),
    $currentRestNamespace . '/v1' => $newTextDomain . '/v1',
    $currentRestNamespace => $newTextDomain,
    $currentThemeUri => $newThemeUri,
    "'WCO', [" => "'{$newJsNamespace}', [",
];

$files = collectFiles($themeDir);
$changedFiles = [];

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        fwrite(STDERR, "Failed to read {$file}\n");
        exit(1);
    }

    $updated = str_replace(array_keys($replacements), array_values($replacements), $contents, $count);
    if ($count === 0 || $updated === $contents) {
        continue;
    }

    $changedFiles[] = $file;
    if (!$dryRun) {
        if (file_put_contents($file, $updated) === false) {
            fwrite(STDERR, "Failed to write {$file}\n");
            exit(1);
        }
    }
}

if ($shouldRenameDirectory) {
    if (!$dryRun && !rename($themeDir, $targetThemeDir)) {
        fwrite(STDERR, "Failed to rename theme directory to {$projectSlug}\n");
        exit(1);
    }
}

echo $dryRun ? "Dry run complete.\n" : "Rename complete.\n";
echo "Theme name: {$currentThemeName} -> {$newThemeName}\n";
echo "Theme slug: {$currentTextDomain} -> {$newTextDomain}\n";
echo "PHP namespace: {$currentPhpNamespace} -> {$newPhpNamespace}\n";
echo "Changed files: " . count($changedFiles) . "\n";

foreach ($changedFiles as $file) {
    $displayPath = str_replace($themeDir . '/', '', $file);
    echo " - {$displayPath}\n";
}

if ($shouldRenameDirectory) {
    echo "Directory: {$currentSlug} -> {$projectSlug}\n";
}

if (!$dryRun) {
    echo "Next: run composer dump-autoload -o and npm run build in the renamed theme directory.\n";
}

function collectFiles(string $themeDir): array
{
    $allowedExtensions = ['php', 'json', 'md', 'css', 'twig'];
    $excludedDirs = [
        $themeDir . '/vendor',
        $themeDir . '/node_modules',
        $themeDir . '/public',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];

    foreach ($iterator as $fileInfo) {
        $path = $fileInfo->getPathname();

        foreach ($excludedDirs as $excludedDir) {
            if (str_starts_with($path, $excludedDir . '/')) {
                continue 2;
            }
        }

        if (!$fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $files[] = $path;
    }

    sort($files);

    return $files;
}

function extractThemeName(string $stylePath): ?string
{
    return extractStyleHeader($stylePath, 'Theme Name');
}

function extractThemeUri(string $stylePath): ?string
{
    return extractStyleHeader($stylePath, 'Theme URI');
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

function extractComposerName(string $composerPath): ?string
{
    return extractJsonProperty($composerPath, 'name');
}

function extractPackageName(string $packagePath): ?string
{
    return extractJsonProperty($packagePath, 'name');
}

function extractJsonProperty(string $jsonPath, string $property): ?string
{
    $contents = @file_get_contents($jsonPath);
    if ($contents === false) {
        return null;
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return null;
    }

    $value = $decoded[$property] ?? null;
    return is_string($value) ? $value : null;
}

function extractPhpNamespace(string $phpPath): ?string
{
    $contents = @file_get_contents($phpPath);
    if ($contents === false) {
        return null;
    }

    if (!preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
        return null;
    }

    $namespace = trim($matches[1]);
    $parts = explode('\\', $namespace);
    array_pop($parts);

    return $parts ? implode('\\', $parts) : null;
}

function extractRestNamespace(string $phpPath): ?string
{
    $contents = @file_get_contents($phpPath);
    if ($contents === false) {
        return null;
    }

    if (!preg_match("/register_rest_route\\('([^']+)\\/v1'/", $contents, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

function buildComposerPackageName(string $slug): string
{
    $parts = explode('-', $slug);
    $vendor = array_shift($parts);
    $package = $parts ? implode('-', $parts) : 'theme';

    return $vendor . '/' . $package;
}

function buildPhpNamespace(string $slug): string
{
    $parts = preg_split('/-+/', $slug) ?: [];
    $studly = array_map(
        static fn(string $part): string => ucfirst($part),
        array_filter($parts, static fn(string $part): bool => $part !== '')
    );

    $base = implode('', $studly);
    if ($base === '') {
        $base = 'Project';
    }

    return $base . '\\Theme';
}

function buildJsNamespace(string $slug): string
{
    $value = strtoupper(str_replace('-', '_', $slug));
    return preg_replace('/[^A-Z0-9_]/', '', $value) ?: 'THEME';
}
