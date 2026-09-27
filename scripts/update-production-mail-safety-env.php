<?php

declare(strict_types=1);

// Only these non-secret settings are changed. This runs while workers are stopped.
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, "Mail safety environment update failed; inspect the environment file on the VPS.\n");
    exit(1);
});

if ($argc !== 2 || ! is_file($argv[1]) || is_link($argv[1])) {
    fwrite(STDERR, "Expected an existing, regular ENV_PATH.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';

$updates = [
    'AI_PRICE_LIST_NOTIFICATIONS_ENABLED' => 'false',
    'AI_PRICE_LIST_MAX_ACK_ENABLED' => 'false',
    'AI_PRICE_LIST_AI_ENABLED' => 'false',
    'PRICE_LIST_AI_RERANKING_ENABLED' => 'false',
    'AI_PRICE_LIST_AUTHORIZATION_ENABLED' => 'true',
];
$path = $argv[1];
$handle = fopen($path, 'r+');
if (! is_resource($handle) || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Environment lock unavailable.');
}

$temporary = null;
try {
    $contents = stream_get_contents($handle);
    $metadata = fstat($handle);
    if (! is_string($contents) || ! is_array($metadata)) {
        throw new RuntimeException('Environment unavailable.');
    }
    $before = Dotenv\Dotenv::parse($contents);
    foreach (array_keys($updates) as $key) {
        if (isset($before[$key]) && strpbrk($before[$key], "\r\n") !== false) {
            throw new RuntimeException('Multiline setting is not supported.');
        }
    }

    $seen = [];
    $pattern = '/^[\t ]*(?:export[\t ]+)?('.implode('|', array_keys($updates)).')[\t ]*=[^\r\n]*(?=\r?$)/m';
    $output = preg_replace_callback($pattern, static function (array $match) use ($updates, &$seen): string {
        $seen[$match[1]] = true;

        return $match[1].'='.$updates[$match[1]];
    }, $contents);
    if (! is_string($output)) {
        throw new RuntimeException('Environment update failed.');
    }
    $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
    foreach ($updates as $key => $value) {
        if (! isset($seen[$key])) {
            $output = rtrim($output, "\r\n").$newline.$key.'='.$value.$newline;
        }
    }
    $after = Dotenv\Dotenv::parse($output);
    if (array_intersect_key($after, $updates) != $updates
        || array_diff_key($before, $updates) !== array_diff_key($after, $updates)) {
        throw new RuntimeException('Unrelated settings must remain unchanged.');
    }

    $temporary = tempnam(dirname($path), '.mail-safety-env-');
    if ($temporary === false) {
        throw new RuntimeException('Temporary environment unavailable.');
    }
    @chown($temporary, $metadata['uid']);
    @chgrp($temporary, $metadata['gid']);
    if (! chmod($temporary, 0600) || file_put_contents($temporary, $output, LOCK_EX) !== strlen($output)
        || ! rename($temporary, $path)) {
        throw new RuntimeException('Environment activation failed.');
    }
} finally {
    if (is_string($temporary) && is_file($temporary)) {
        unlink($temporary);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
}

fwrite(STDOUT, "Price-list AI and notifications disabled; authorization enabled.\n");
