<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// The caller holds the production deployment lock for the entire rollout.
if ($argc !== 2 || ! is_file($argv[1]) || is_link($argv[1])) {
    fwrite(STDERR, "Expected an existing, non-symlink ENV_PATH.\n");
    exit(2);
}

$envPath = $argv[1];
$contents = file_get_contents($envPath);
if (! is_string($contents)) {
    throw new RuntimeException('Production environment could not be read.');
}

$parseEnvironment = static function (string $input): array {
    $sentinel = 'REALTIME_PARSE_SENTINEL_'.strtoupper(bin2hex(random_bytes(8)));
    try {
        // phpdotenv otherwise silently ignores an unterminated final multiline
        // value. A sentinel proves that the complete file was consumed.
        $parsed = Dotenv\Dotenv::parse($input."\n{$sentinel}=complete\n");
        if (($parsed[$sentinel] ?? null) !== 'complete') {
            throw new RuntimeException('Incomplete environment.');
        }
        unset($parsed[$sentinel]);

        return $parsed;
    } catch (Throwable) {
        // Dotenv exception messages can contain lines with production secrets.
        throw new RuntimeException('Production environment could not be parsed.');
    }
};
$values = $parseEnvironment($contents);

$url = parse_url($values['APP_URL'] ?? '');
if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
    || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/D', $url['host'] ?? '') !== 1
    || ($url['port'] ?? 443) !== 443
    || ! in_array($url['path'] ?? '', ['', '/'], true)
    || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
    throw new RuntimeException('Realtime production requires APP_URL with an HTTPS hostname on port 443 and no subdirectory.');
}

$updates = [
    'REALTIME_ENABLED' => 'true',
    'REALTIME_QUEUE_CONNECTION' => 'database',
    'REALTIME_QUEUE' => 'realtime',
    'REALTIME_WS_HOST' => strtolower($url['host']),
    'REALTIME_WS_PORT' => '443',
    'REALTIME_WS_SCHEME' => 'https',
    'REALTIME_WS_PATH' => '/realtime',
    'BROADCAST_CONNECTION' => 'reverb',
    'REVERB_SERVER_HOST' => '127.0.0.1',
    'REVERB_SERVER_PORT' => '8085',
    'REVERB_SERVER_PATH' => '',
    'REVERB_HOST' => '127.0.0.1',
    'REVERB_PORT' => '8085',
    'REVERB_SCHEME' => 'http',
    'REVERB_SCALING_ENABLED' => 'false',
    'REVERB_ALLOWED_ORIGINS' => strtolower($url['host']),
];

foreach (['REVERB_APP_ID' => 16, 'REVERB_APP_KEY' => 24, 'REVERB_APP_SECRET' => 32] as $name => $bytes) {
    $value = $values[$name] ?? '';
    $minimumLength = $name === 'REVERB_APP_ID' ? 1 : 8;
    // Keep existing credentials stable across deployments. Refuse malformed
    // values instead of silently disconnecting clients by rotating a live key.
    if ($value !== '' && preg_match('/^[A-Za-z0-9_-]{'.$minimumLength.',128}$/D', $value) !== 1) {
        throw new RuntimeException('An existing Reverb credential has an unsupported format.');
    }
    $updates[$name] = $value !== '' ? $value : bin2hex(random_bytes($bytes));
}

$lines = preg_split('/\R/', $contents) ?: [];
$seen = [];
foreach ($lines as &$line) {
    if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $matches) !== 1
        || ! array_key_exists($matches[1], $updates)) {
        continue;
    }
    $name = $matches[1];
    // Remove duplicate declarations, including indented/exported legacy lines.
    $line = isset($seen[$name]) ? null : $name.'='.$updates[$name];
    $seen[$name] = true;
}
unset($line);
foreach ($updates as $name => $value) {
    if (! isset($seen[$name])) {
        $lines[] = $name.'='.$value;
    }
}

$output = rtrim(implode("\n", array_filter($lines, static fn ($line) => $line !== null)), "\n")."\n";
$expected = array_replace($values, $updates);
$actual = $parseEnvironment($output);
ksort($expected);
ksort($actual);
if ($actual !== $expected) {
    throw new RuntimeException('Production environment update would change unrelated values.');
}
$metadata = stat($envPath);
$temporaryPath = tempnam(dirname($envPath), '.realtime-env-');
if ($metadata === false || $temporaryPath === false) {
    throw new RuntimeException('Production environment could not be staged.');
}

try {
    if (! chmod($temporaryPath, 0600)
        || file_put_contents($temporaryPath, $output, LOCK_EX) !== strlen($output)
        || ! chown($temporaryPath, $metadata['uid']) || ! chgrp($temporaryPath, $metadata['gid'])
        || ! chmod($temporaryPath, $metadata['mode'] & 0640)
        || ! rename($temporaryPath, $envPath)) {
        throw new RuntimeException('Production environment update could not be activated.');
    }
} finally {
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}
