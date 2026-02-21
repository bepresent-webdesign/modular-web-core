#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PHP 8+ CLI build script.
 * Produces a clean, installable commercial ZIP package from the repository root.
 */

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "build.php: failed to resolve project root\n");
    exit(1);
}

$versionFile = $projectRoot . '/engine-version.json';
if (!is_readable($versionFile)) {
    fwrite(STDERR, "build.php: engine-version.json not found or not readable\n");
    exit(1);
}

$versionData = json_decode(file_get_contents($versionFile), true);
if (!is_array($versionData)) {
    fwrite(STDERR, "build.php: engine-version.json is invalid JSON\n");
    exit(1);
}

$version = $versionData['version'] ?? $versionData['engine_version'] ?? null;
if ($version === null || $version === '') {
    fwrite(STDERR, "build.php: version or engine_version key missing in engine-version.json\n");
    exit(1);
}

if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$/', $version)) {
    fwrite(STDERR, "build.php: invalid version format: {$version}\n");
    exit(1);
}

$distDir = $projectRoot . '/dist';
if (!is_dir($distDir)) {
    if (!mkdir($distDir, 0755, true)) {
        fwrite(STDERR, "build.php: failed to create dist directory\n");
        exit(1);
    }
}

$zipPath = $distDir . '/modular-web-core-' . $version . '.zip';

$excludedPrefixes = [
    '.git',
    '.github',
    'build',
    'dist',
    'docs',
    'tests',
    'test',
    'node_modules',
    'vendor',
];

$emptyDirNames = ['data', 'uploads', 'backups'];

$excludedFiles = ['.DS_Store', 'Thumbs.db'];

function shouldExclude(string $relPath, array $excludedPrefixes, array $excludedFiles): bool
{
    $relPath = str_replace('\\', '/', $relPath);
    foreach ($excludedPrefixes as $prefix) {
        if ($relPath === $prefix || str_starts_with($relPath, $prefix . '/')) {
            return true;
        }
    }
    foreach ($excludedFiles as $name) {
        if (basename($relPath) === $name) {
            return true;
        }
    }
    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
    if ($ext === 'log' || $ext === 'tmp') {
        return true;
    }
    $base = basename($relPath);
    if ($base === '.env' || str_starts_with($base, '.env.')) {
        return true;
    }
    return false;
}

function isEmptyDir(string $relPath, array $emptyDirNames): bool
{
    $relPath = str_replace('\\', '/', trim($relPath, '/\\'));
    foreach ($emptyDirNames as $dir) {
        if ($relPath === $dir || str_starts_with($relPath, $dir . '/')) {
            return true;
        }
    }
    return false;
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "build.php: failed to create zip file: {$zipPath}\n");
    exit(1);
}

foreach ($emptyDirNames as $name) {
    $zip->addEmptyDir($name . '/');
}

$fileCount = 0;
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $spl) {
    $absPath = $spl->getPathname();
    $relPath = substr($absPath, strlen($projectRoot) + 1);
    $relPath = str_replace('\\', '/', $relPath);

    if (shouldExclude($relPath, $excludedPrefixes, $excludedFiles)) {
        continue;
    }

    if (isEmptyDir($relPath, $emptyDirNames)) {
        continue;
    }

    if ($spl->isDir()) {
        $zip->addEmptyDir($relPath . '/');
    } else {
        $zip->addFile($absPath, $relPath);
        $fileCount++;
    }
}

$zip->close();

echo "Version: {$version}\n";
echo "Zip file: {$zipPath}\n";
echo "Files added: {$fileCount}\n";

exit(0);
