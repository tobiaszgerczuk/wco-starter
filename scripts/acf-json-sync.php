#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$args = $argv;
array_shift($args);

$action = $args[0] ?? '';
if (in_array($action, ['-h', '--help'], true) || $action === '') {
    $themeDir = realpath(__DIR__ . '/..');
    $defaultSourceHint = $themeDir !== false ? $themeDir . '/views/blocks' : '/path/to/theme/views/blocks';

    $help = <<<TXT
Usage:
  php scripts/acf-json-sync.php pull [--source=PATH] [--target=PATH] [--dry-run]
  php scripts/acf-json-sync.php push [--source=PATH] [--target=PATH] [--dry-run]
  php scripts/acf-json-sync.php diff [--source=PATH] [--target=PATH]

Shortcuts:
  acf:pull  runs: pull --source=<remote>/views/blocks --target=<theme>/views/blocks
  acf:push  runs: push --source=<theme>/views/blocks --target=<remote>/views/blocks

Options:
  --source=PATH     Source directory with ACF JSON files.
  --target=PATH     Target directory where files should be synced to.
  --dry-run         Show what will happen without changing files.

Examples:
  php scripts/acf-json-sync.php pull --source=/srv/prod/wp-content/themes/my-theme/views/blocks
  php scripts/acf-json-sync.php push --target=/srv/stage/wp-content/themes/my-theme/views/blocks --dry-run

TXT;

    echo $help;
    echo "\nTheme folder: {$defaultSourceHint}\n";
    exit(0);
}

if (!in_array($action, ['pull', 'push', 'diff'], true)) {
    fwrite(STDERR, "Unknown action: {$action}\nUse --help for usage.\n");
    exit(1);
}

$isPull = $action === 'pull';
$isPush = $action === 'push';
$isDiff = $action === 'diff';

$themeDir = realpath(__DIR__ . '/..');
if ($themeDir === false) {
    fwrite(STDERR, "Could not resolve theme directory.\n");
    exit(1);
}

$themeBlocksDir = $themeDir . '/views/blocks';
$source = null;
$target = null;
$dryRun = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $source = substr($arg, 9);
        continue;
    }

    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, 9);
        continue;
    }

    if ($arg === '--dry-run') {
        $dryRun = true;
    }
}

if ($isPull) {
    $source = $source ?? '';
    $target = $target ?? $themeBlocksDir;
}

if ($isPush) {
    $source = $source ?? $themeBlocksDir;
    $target = $target ?? '';
}

if ($isDiff) {
    $source = $source ?? '';
    $target = $target ?? $themeBlocksDir;
}

if (!is_string($source) || $source === '') {
    fwrite(STDERR, "Source path is required. Use --source=... when using pull/diff, for push source defaults to <theme>/views/blocks.\n");
    exit(1);
}

if (!is_string($target) || $target === '') {
    $message = $isPull ? 'Target path is required for pull. Use --target=... or accept default <theme>/views/blocks.' : 'Target path is required. Use --target=...';
    if ($isPush || $isDiff) {
        $message = 'Target path is required. Use --target=...';
    }
    fwrite(STDERR, "{$message}\n");
    exit(1);
}

if (!is_dir($source)) {
    fwrite(STDERR, "Source path does not exist or is not a directory: {$source}\n");
    exit(1);
}

$source = rtrim((string) realpath($source), DIRECTORY_SEPARATOR);
$resolvedTarget = realpath($target);
$target = $resolvedTarget === false ? rtrim((string) $target, DIRECTORY_SEPARATOR) : rtrim((string) $resolvedTarget, DIRECTORY_SEPARATOR);

if (!is_dir($target)) {
    $targetParent = dirname($target);
    if (!is_dir($targetParent)) {
        fwrite(STDERR, "Target path parent does not exist: {$targetParent}\n");
        exit(1);
    }

    if (!mkdir($target, 0755, true) && !is_dir($target)) {
        fwrite(STDERR, "Could not create target directory: {$target}\n");
        exit(1);
    }
}

if (!is_readable($source) || !is_writable($target)) {
    fwrite(STDERR, "Source and target must be readable/writable.\n");
    exit(1);
}

$sourceFiles = scan_acf_json_files($source);
$targetFiles = scan_acf_json_files($target);

$summary = compare_file_sets($sourceFiles, $targetFiles);
echo "\nACF JSON sync plan\n";
echo "Source: {$source}\n";
echo "Target: {$target}\n\n";

if ($summary['added'] === [] && $summary['removed'] === [] && $summary['changed'] === []) {
    echo "No differences.\n";
    if ($isDiff) {
        exit(0);
    }
}

if (!empty($summary['added'])) {
    echo "Add/overwrite:\n";
    foreach ($summary['added'] as $fileName) {
        echo "  + {$fileName}\n";
    }
}

if (!empty($summary['changed'])) {
    echo "Update:\n";
    foreach ($summary['changed'] as $fileName) {
        echo "  ~ {$fileName}\n";
    }
}

if (!empty($summary['removed'])) {
    echo "Delete:\n";
    foreach ($summary['removed'] as $fileName) {
        echo "  - {$fileName}\n";
    }
}

if ($isDiff) {
    exit(empty($summary['added']) && empty($summary['changed']) && empty($summary['removed']) ? 0 : 2);
}

if ($dryRun) {
    echo "\nDry run: no files changed.\n";
    exit(empty($summary['added']) && empty($summary['changed']) && empty($summary['removed']) ? 0 : 0);
}

backup_path($target);

foreach ($summary['removed'] as $file) {
    $path = $target . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path) && !unlink($path)) {
        fwrite(STDERR, "Failed to delete: {$path}\n");
        exit(1);
    }
}

foreach ($summary['added'] as $file) {
    $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
    $targetPath = $target . DIRECTORY_SEPARATOR . $file;
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        fwrite(STDERR, "Could not create target file directory: {$targetDir}\n");
        exit(1);
    }
    if (!copy($sourcePath, $targetPath)) {
        fwrite(STDERR, "Failed to copy: {$sourcePath}\n");
        exit(1);
    }
}

foreach ($summary['changed'] as $file) {
    $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
    $targetPath = $target . DIRECTORY_SEPARATOR . $file;
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        fwrite(STDERR, "Could not create target file directory: {$targetDir}\n");
        exit(1);
    }
    if (!copy($sourcePath, $targetPath)) {
        fwrite(STDERR, "Failed to copy: {$sourcePath}\n");
        exit(1);
    }
}

echo "\nSync complete.\n";
exit(0);

function scan_acf_json_files(string $dir): array
{
    $files = [];
    if (!is_dir($dir)) {
        return $files;
    }

    $basePath = rtrim((string) realpath($dir), DIRECTORY_SEPARATOR);
    if ($basePath === '') {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        if ($item->getExtension() !== 'json' || !str_starts_with($item->getFilename(), 'group_')) {
            continue;
        }

        $path = $item->getPathname();
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            continue;
        }

        $relativePath = ltrim(str_replace($basePath, '', $path), DIRECTORY_SEPARATOR);
        $files[$relativePath] = [
            'path' => $path,
            'relative_path' => $relativePath,
            'hash' => md5($content),
            'size' => $item->getSize(),
            'modified' => $item->getMTime(),
        ];
    }

    ksort($files);
    return $files;
}

function compare_file_sets(array $source, array $target): array
{
    $added = [];
    $removed = [];
    $changed = [];

    foreach ($source as $name => $file) {
        if (!isset($target[$name])) {
            $added[] = $name;
            continue;
        }

        if ($file['hash'] !== $target[$name]['hash'] || $file['size'] !== $target[$name]['size']) {
            $changed[] = $name;
        }
    }

    foreach ($target as $name => $_file) {
        if (!isset($source[$name])) {
            $removed[] = $name;
        }
    }

    return [
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
    ];
}

function backup_path(string $target): void
{
    $backupRoot = $target . DIRECTORY_SEPARATOR . '.acf-backup';
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0755, true)) {
        fwrite(STDERR, "Could not create backup root: {$backupRoot}\n");
        exit(1);
    }

    $stamp = gmdate('Y-m-d_H-i-s');
    $snapshot = $backupRoot . DIRECTORY_SEPARATOR . $stamp;
    if (!mkdir($snapshot, 0755, true)) {
        fwrite(STDERR, "Could not create backup snapshot: {$snapshot}\n");
        exit(1);
    }

    foreach (scan_acf_json_files($target) as $name => $file) {
        $source = $file['path'];
        $destination = $snapshot . DIRECTORY_SEPARATOR . $file['relative_path'];
        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            fwrite(STDERR, "Could not create backup path: {$dir}\n");
            exit(1);
        }
        copy($source, $destination);
    }
}
