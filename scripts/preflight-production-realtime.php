<?php

declare(strict_types=1);

// This file and its Nginx parser are staged from the requested Git commit before
// the production checkout is changed. The existing Composer autoloader suffices.
if ($argc !== 3) {
    fwrite(STDERR, "Expected TARGET_DIR and NGINX_DUMP.\n");
    exit(2);
}

require $argv[1].'/vendor/autoload.php';
require __DIR__.'/lib/RealtimeNginx.php';

try {
    $values = Dotenv\Dotenv::parse((string) file_get_contents($argv[1].'/.env'));
} catch (Throwable) {
    fwrite(STDERR, "The server environment could not be parsed.\n");
    exit(1);
}

try {
    if (preg_match('~^/[A-Za-z0-9_./-]+$~D', $argv[1]) !== 1 || $argv[1] === '/') {
        throw new RuntimeException('The realtime deployment application path is unsafe.');
    }
    $url = parse_url($values['APP_URL'] ?? '');
    if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
        || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/D', $url['host'] ?? '') !== 1
        || ($url['port'] ?? 443) !== 443
        || ! in_array($url['path'] ?? '', ['', '/'], true)
        || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
        throw new RuntimeException('Realtime production requires APP_URL with an HTTPS hostname on port 443 and no subdirectory.');
    }

    $plan = Pischeprom\Deployment\RealtimeNginx::plan(
        (string) file_get_contents($argv[2]), $argv[1], strtolower($url['host'])
    );
    if (is_link('/etc/nginx/snippets') || is_link('/etc/nginx/snippets/pischeprom-realtime.conf')) {
        throw new RuntimeException('The managed Nginx snippet location is unsafe.');
    }
    fwrite(STDOUT, $plan['path']);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Realtime Nginx preflight could not be completed.\n");
    exit(1);
}
