<?php

declare(strict_types=1);

// Credentials arrive in a private, short-lived deployment file, never argv or logs.
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, "Yandex Maps configuration could not be validated or saved; no credentials were logged.\n");
    exit(1);
});

if ($argc < 3 || $argc > 4 || ($argc === 4 && $argv[3] !== '--check')) {
    fwrite(STDERR, "Expected ENV_PATH CONFIG_JSON [--check].\n");
    exit(2);
}

[$envPath, $configPath] = [$argv[1], $argv[2]];
$checkOnly = $argc === 4;
foreach ([$envPath, $configPath] as $path) {
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Unsafe configuration file.');
    }
}
$autoload = dirname($envPath).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__).'/vendor/autoload.php';
$input = json_decode(file_get_contents($configPath), true, 8, JSON_THROW_ON_ERROR);
$key = $input['api_key'] ?? null;
if (! is_string($key) || preg_match('/\A[A-Za-z0-9_-]{16,256}\z/', $key) !== 1) {
    throw new RuntimeException('Invalid key.');
}
$script = $input['script_url'] ?? '';
if (! is_string($script) || ($script !== ''
    && preg_match('~\Ahttps://(?:api-maps|enterprise\.api-maps)\.yandex\.ru/2\.1/?\z~', $script) !== 1)) {
    throw new RuntimeException('Untrusted map script.');
}

$handle = fopen($envPath, 'r');
if (! is_resource($handle) || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Cannot lock environment.');
}
try {
    $contents = stream_get_contents($handle);
    $metadata = fstat($handle);
    if (! is_string($contents) || $metadata === false) {
        throw new RuntimeException('Cannot read environment.');
    }
    $before = Dotenv\Dotenv::parse($contents);
    $updates = ['YANDEX_MAPS_API_KEY' => $key];
    if ($script !== '') {
        $updates['YANDEX_MAP_SCRIPT_URL'] = $script;
    }
    $seen = [];
    $pattern = '/^[\t ]*(?:export[\t ]+)?('.implode('|', array_keys($updates)).')[\t ]*=[^\r\n]*(?=\r?$)/m';
    $output = preg_replace_callback($pattern, static function (array $match) use ($updates, &$seen): string {
        $seen[$match[1]] = true;

        return $match[1].'='.$updates[$match[1]];
    }, $contents);
    $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
    foreach ($updates as $name => $value) {
        if (! isset($seen[$name])) {
            if ($output !== '' && ! str_ends_with($output, "\n")) {
                $output .= $newline;
            }
            $output .= $name.'='.$value.$newline;
        }
    }
    $after = Dotenv\Dotenv::parse($output);
    if (array_diff_key($before, $updates) !== array_diff_key($after, $updates)
        || array_intersect_key($after, $updates) != $updates) {
        throw new RuntimeException('Unexpected environment change.');
    }
    if (! $checkOnly) {
        $temporary = tempnam(dirname($envPath), '.yandex-maps-env-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot stage environment.');
        }
        try {
            @chown($temporary, $metadata['uid']);
            @chgrp($temporary, $metadata['gid']);
            if (! chmod($temporary, 0600)
                || file_put_contents($temporary, $output, LOCK_EX) !== strlen($output)
                || ! rename($temporary, $envPath)) {
                throw new RuntimeException('Cannot activate environment.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}
fwrite(STDOUT, $checkOnly ? "Yandex Maps configuration preflight passed.\n" : "Yandex Maps configuration installed.\n");
