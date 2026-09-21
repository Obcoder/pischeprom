<?php

return [
    'paths' => ['api/mobile/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    // Capacitor Android uses https://localhost. Add a browser dev origin explicitly.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim', explode(',', env('MOBILE_ALLOWED_ORIGINS', 'https://localhost'))
    ), static fn (string $origin): bool => $origin !== '' && ! str_contains($origin, '*'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
