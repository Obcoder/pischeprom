#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: validate-map-assets.php /absolute/path/map-assets\n");
    exit(64);
}

$base = realpath($argv[1]);
if ($base === false || ! is_dir($base) || is_link($base)) {
    fwrite(STDERR, "Map assets root must be a real directory.\n");
    exit(1);
}

$manifest = $base.DIRECTORY_SEPARATOR.'SHA256SUMS';
if (! is_file($manifest) || is_link($manifest)) {
    fwrite(STDERR, "Map assets SHA256SUMS must be a regular file.\n");
    exit(1);
}

$expected = [];
foreach (file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $lineNumber => $line) {
    if (! preg_match('/^([0-9a-fA-F]{64})[ \t]+\*?(.+)$/', $line, $matches)) {
        fwrite(STDERR, 'Invalid SHA256SUMS line '.($lineNumber + 1).".\n");
        exit(1);
    }

    $relative = str_replace('\\', '/', trim($matches[2]));
    if ($relative === '' || str_starts_with($relative, '/')
        || ! preg_match('#^(fonts|licenses|sprites)/#', $relative)
        || in_array('..', explode('/', $relative), true)
        || isset($expected[$relative])) {
        fwrite(STDERR, 'Unsafe or duplicate map asset path on line '.($lineNumber + 1).".\n");
        exit(1);
    }
    $expected[$relative] = strtolower($matches[1]);
}

if ($expected === []) {
    fwrite(STDERR, "Map assets checksum manifest is empty.\n");
    exit(1);
}

$actual = [];
foreach (['fonts', 'licenses', 'sprites'] as $rootName) {
    $root = $base.DIRECTORY_SEPARATOR.$rootName;
    if (! is_dir($root) || is_link($root)) {
        fwrite(STDERR, "Map assets {$rootName} root must be a real directory.\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isLink() || ! $file->isFile()) {
            fwrite(STDERR, "Map assets may contain only regular files and directories.\n");
            exit(1);
        }
        $path = $file->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($base) + 1));
        $actual[$relative] = hash_file('sha256', $path);
    }
}

ksort($expected);
ksort($actual);
if (array_keys($actual) !== array_keys($expected)) {
    fwrite(STDERR, "SHA256SUMS must list every published font/sprite file exactly once.\n");
    exit(1);
}

foreach ($expected as $relative => $checksum) {
    if (! hash_equals($checksum, $actual[$relative])) {
        fwrite(STDERR, "Map asset checksum mismatch: {$relative}.\n");
        exit(1);
    }
}

fwrite(STDOUT, 'Map assets verified: '.count($actual)." files.\n");
