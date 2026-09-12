<?php

declare(strict_types=1);

class GoodsSeoAiEnvironmentException extends RuntimeException {}

set_exception_handler(static function (Throwable $exception): void {
    // Never emit traces or arbitrary dependency messages from a secret-bearing parser.
    fwrite(STDERR, $exception instanceof GoodsSeoAiEnvironmentException
        ? $exception->getMessage()."\n"
        : "Goods SEO AI environment validation or activation failed.\n");
    exit(1);
});

if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '--check')) {
    fwrite(STDERR, "Expected ENV_PATH [--check].\n");
    exit(2);
}

$envPath = $argv[1];
$checkOnly = $argc === 3;

if (! is_file($envPath) || is_link($envPath)) {
    throw new GoodsSeoAiEnvironmentException('Production environment file is missing or unsafe.');
}

// The selected deployment script may be staged outside the application checkout.
$autoloadPath = dirname($envPath).'/vendor/autoload.php';
if (! is_file($autoloadPath)) {
    $autoloadPath = dirname(__DIR__).'/vendor/autoload.php';
}
if (! is_file($autoloadPath)) {
    throw new GoodsSeoAiEnvironmentException('Application dependencies are required for environment validation.');
}
require $autoloadPath;

$handle = fopen($envPath, $checkOnly ? 'r' : 'r+');
if (! is_resource($handle) || ! flock($handle, $checkOnly ? LOCK_SH : LOCK_EX)) {
    throw new GoodsSeoAiEnvironmentException('Production environment file could not be locked.');
}

try {
    $contents = stream_get_contents($handle);
    $metadata = fstat($handle);
    if (! is_string($contents) || $metadata === false) {
        throw new GoodsSeoAiEnvironmentException('Production environment file could not be read.');
    }

    $values = parseSeoEnvironment($contents);
    $key = seoTimewebKey($values);
    if (! is_string($key) || preg_match('/^[\x21-\x7e]{1,4096}$/D', $key) !== 1) {
        throw new GoodsSeoAiEnvironmentException('A valid existing Timeweb API key is required for goods SEO AI.');
    }

    // Explicitly authorized production settings; credentials and other AI gates stay intact.
    $updates = [
        'GOODS_SEO_AI_ENABLED' => 'true',
        'GOODS_SEO_AI_MODEL' => 'yandex/yandexgpt-pro-5.1',
        'GOODS_SEO_AI_TOKEN_PARAMETER' => 'max_tokens',
        'GOODS_SEO_AI_TIMEOUT_SECONDS' => '45',
    ];
    foreach (array_keys($updates) as $name) {
        if (isset($values[$name]) && strpbrk($values[$name], "\r\n") !== false) {
            throw new GoodsSeoAiEnvironmentException('Goods SEO AI settings must use single-line environment values.');
        }
    }

    $seen = [];
    $pattern = '/^[\t ]*(?:export[\t ]+)?('.implode('|', array_keys($updates)).')[\t ]*=[^\r\n]*(?=\r?$)/m';
    $output = preg_replace_callback($pattern, static function (array $matches) use ($updates, &$seen): string {
        $seen[$matches[1]] = true;

        return $matches[1].'='.$updates[$matches[1]];
    }, $contents);
    if (! is_string($output)) {
        throw new GoodsSeoAiEnvironmentException('Production environment settings could not be prepared.');
    }

    $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
    foreach ($updates as $name => $value) {
        if (! isset($seen[$name])) {
            if ($output !== '' && ! str_ends_with($output, "\n")) {
                $output .= $newline;
            }
            $output .= $name.'='.$value.$newline;
        }
    }

    $updatedValues = parseSeoEnvironment($output);
    if (seoTimewebKey($updatedValues) !== $key
        || array_intersect_key($updatedValues, $updates) != $updates
        || array_diff_key($updatedValues, $updates) !== array_diff_key($values, $updates)) {
        throw new GoodsSeoAiEnvironmentException('Goods SEO AI settings could not be validated without changing credentials.');
    }

    if ($checkOnly) {
        fwrite(STDOUT, "Goods SEO AI environment preflight passed.\n");
        exit(0);
    }

    $temporaryPath = tempnam(dirname($envPath), '.goods-seo-ai-env-');
    if ($temporaryPath === false) {
        throw new GoodsSeoAiEnvironmentException('Temporary environment file could not be created.');
    }

    try {
        @chown($temporaryPath, $metadata['uid']);
        @chgrp($temporaryPath, $metadata['gid']);
        if (! chmod($temporaryPath, 0600)
            || file_put_contents($temporaryPath, $output, LOCK_EX) !== strlen($output)) {
            throw new GoodsSeoAiEnvironmentException('Production environment update was incomplete.');
        }
        if (! rename($temporaryPath, $envPath)) {
            throw new GoodsSeoAiEnvironmentException('Production environment update could not be activated.');
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

fwrite(STDOUT, "Goods SEO AI enabled with yandex/yandexgpt-pro-5.1.\n");

function parseSeoEnvironment(string $contents): array
{
    try {
        return Dotenv\Dotenv::parse($contents);
    } catch (Throwable) {
        // Dotenv parser errors can contain the offending line, including a secret.
        throw new GoodsSeoAiEnvironmentException('Production environment syntax is invalid.');
    }
}

function seoTimewebKey(array $values): mixed
{
    $normalize = static fn ($value) => is_string($value) ? match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => $value,
    } : $value;

    return $normalize($values['GOODS_SEO_AI_API_KEY'] ?? null)
        ?: $normalize($values['AI_TIMEWEB_LOCAL_RU_API_KEY'] ?? null);
}
