<?php

$applicationUrl = parse_url((string) env('APP_URL', 'http://localhost'));
$scheme = env('REALTIME_WS_SCHEME', $applicationUrl['scheme'] ?? 'http');

return [
    'enabled' => (bool) env('REALTIME_ENABLED', false),
    'queue_connection' => env('REALTIME_QUEUE_CONNECTION', 'database'),
    'queue' => env('REALTIME_QUEUE', 'realtime'),
    'channel' => 'commerce.updates',
    'client' => [
        'host' => env('REALTIME_WS_HOST', $applicationUrl['host'] ?? 'localhost'),
        'port' => (int) env('REALTIME_WS_PORT', $applicationUrl['port'] ?? ($scheme === 'https' ? 443 : 80)),
        'scheme' => $scheme,
        'path' => env('REALTIME_WS_PATH', '/realtime'),
    ],
];
