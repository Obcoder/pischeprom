<?php

declare(strict_types=1);

if ($argc !== 5) {
    fwrite(STDERR, "Expected ENV_PATH, API_KEY_FILE, FOLDER_ID and CLAMAV_SOCKET.\n");
    exit(2);
}

[$script, $envPath, $apiKeyPath, $folderId, $clamAvSocket] = $argv;

if (! is_file($envPath) || is_link($envPath)) {
    throw new RuntimeException('Production environment file is missing or unsafe.');
}

if (! is_file($apiKeyPath) || is_link($apiKeyPath)) {
    throw new RuntimeException('API key input file is missing or unsafe.');
}

if (preg_match('/^[a-z0-9]{20}$/', $folderId) !== 1) {
    throw new RuntimeException('Yandex folder ID has an unexpected format.');
}

if (! str_starts_with($clamAvSocket, '/') || str_contains($clamAvSocket, "\0")) {
    throw new RuntimeException('ClamAV socket path is unsafe.');
}

$apiKey = trim((string) file_get_contents($apiKeyPath));

if (preg_match('/^AQVN[A-Za-z0-9_-]{30,80}$/', $apiKey) !== 1) {
    throw new RuntimeException('Yandex API key has an unexpected format.');
}

$updates = [
    'AI_PRICE_LISTS_ENABLED' => 'true',
    'AI_PRICE_LIST_QUEUE_CONNECTION' => 'redis',
    'AI_PRICE_LIST_QUEUE' => 'price-lists',
    'AI_PRICE_LIST_STORAGE_DISK' => 'yandex',
    'PRICE_LIST_AUTO_APPLY' => 'false',
    'PRICE_LIST_FILE_SCANNER' => 'clamav',
    'PRICE_LIST_CLAMAV_SOCKET' => $clamAvSocket,
    'AI_PROVIDER' => 'yandex',
    'YANDEX_CLOUD_FOLDER_ID' => $folderId,
    'YANDEX_AI_API_KEY' => $apiKey,
    'YANDEX_AI_DATA_LOGGING' => 'false',
];

$handle = fopen($envPath, 'c+');

if (! is_resource($handle) || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Production environment file could not be locked.');
}

try {
    rewind($handle);
    $contents = stream_get_contents($handle);

    if (! is_string($contents)) {
        throw new RuntimeException('Production environment file could not be read.');
    }

    $lines = preg_split('/\R/', $contents) ?: [];
    $seen = [];

    foreach ($lines as &$line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches) !== 1) {
            continue;
        }

        $name = $matches[1];

        if (! array_key_exists($name, $updates)) {
            continue;
        }

        $line = $name.'='.dotenvValue($updates[$name]);
        $seen[$name] = true;
    }
    unset($line);

    foreach ($updates as $name => $value) {
        if (! isset($seen[$name])) {
            $lines[] = $name.'='.dotenvValue($value);
        }
    }

    $output = rtrim(implode("\n", $lines), "\n")."\n";
    $directory = dirname($envPath);
    $temporaryPath = tempnam($directory, '.price-list-env-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Temporary environment file could not be created.');
    }

    try {
        chmod($temporaryPath, 0600);

        if (file_put_contents($temporaryPath, $output, LOCK_EX) !== strlen($output)) {
            throw new RuntimeException('Production environment update was incomplete.');
        }

        if (! rename($temporaryPath, $envPath)) {
            throw new RuntimeException('Production environment update could not be activated.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}

function dotenvValue(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_\/.:-]+$/', $value) === 1) {
        return $value;
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}
