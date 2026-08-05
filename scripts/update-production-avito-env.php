<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Expected ENV_PATH.\n");
    exit(2);
}

$envPath = $argv[1];

if (! is_file($envPath) || is_link($envPath)) {
    throw new RuntimeException('Production environment file is missing or unsafe.');
}

$contents = file_get_contents($envPath);

if (! is_string($contents)) {
    throw new RuntimeException('Production environment file could not be read.');
}

$webhookSecret = environmentValue($contents, 'AVITO_WEBHOOK_SECRET');

if ($webhookSecret === null || preg_match('/^[A-Za-z0-9_-]{43,128}$/', $webhookSecret) !== 1) {
    $webhookSecret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
}

$updates = [
    'AVITO_ENABLED' => 'true',
    'AVITO_API_URL' => 'https://api.avito.ru',
    'AVITO_AUTOTEKA_API_URL' => 'https://pro.autoteka.ru',
    'AVITO_TOKEN_URL' => 'https://api.avito.ru/token',
    'AVITO_AUTHORIZE_URL' => 'https://avito.ru/oauth',
    // Ameise intentionally has no common authorization yet.
    'AVITO_MUTATIONS_ENABLED' => 'false',
    'AVITO_WEBHOOK_SECRET' => $webhookSecret,
];

$handle = fopen($envPath, 'c+');

if (! is_resource($handle) || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Production environment file could not be locked.');
}

try {
    rewind($handle);
    $lockedContents = stream_get_contents($handle);

    if (! is_string($lockedContents)) {
        throw new RuntimeException('Production environment file could not be read while locked.');
    }

    // Preserve a valid secret written between the first read and the lock.
    $lockedSecret = environmentValue($lockedContents, 'AVITO_WEBHOOK_SECRET');
    if ($lockedSecret !== null && preg_match('/^[A-Za-z0-9_-]{43,128}$/', $lockedSecret) === 1) {
        $updates['AVITO_WEBHOOK_SECRET'] = $lockedSecret;
    }

    $lines = preg_split('/\R/', $lockedContents) ?: [];
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
    $temporaryPath = tempnam(dirname($envPath), '.avito-env-');

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

function environmentValue(string $contents, string $name): ?string
{
    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        if (preg_match('/^'.preg_quote($name, '/').'=(.*)$/', $line, $matches) !== 1) {
            continue;
        }

        $value = trim($matches[1]);

        if (strlen($value) >= 2
            && (($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    return null;
}

function dotenvValue(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_\/.:-]+$/', $value) === 1) {
        return $value;
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}
