<?php

$apiBaseUrl = rtrim((string) env('AVITO_API_URL', 'https://api.avito.ru'), '/');

// The unfinished legacy integration documented AVITO_API_URL as the token
// endpoint. Normalize that one known value without accepting arbitrary paths.
if ($apiBaseUrl === 'https://api.avito.ru/token') {
    $apiBaseUrl = 'https://api.avito.ru';
}

return [
    'enabled' => (bool) env('AVITO_ENABLED', true),

    'client_id' => env('AVITO_CLIENT_ID'),
    'client_secret' => env('AVITO_CLIENT_SECRET'),

    'api_base_url' => $apiBaseUrl,
    'autoteka_base_url' => env('AVITO_AUTOTEKA_API_URL', 'https://pro.autoteka.ru'),
    'token_url' => env('AVITO_TOKEN_URL', 'https://api.avito.ru/token'),
    'authorize_url' => env('AVITO_AUTHORIZE_URL', 'https://avito.ru/oauth'),
    'redirect_uri' => env('AVITO_REDIRECT_URI'),

    'oauth_scopes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AVITO_OAUTH_SCOPES', implode(',', [
            'ah:access',
            'autoload:reports',
            'cpa-auction:bids',
            'cpxpromo:edit',
            'cpxpromo:read',
            'items:apply_bbip',
            'items:apply_vas',
            'items:info',
            'job:applications',
            'job:cv',
            'job:vacancy',
            'job:write',
            'messenger:read',
            'messenger:write',
            'ratings:read',
            'ratings:write',
            'short_term_rent:read',
            'short_term_rent:write',
            'special_offers:sending',
            'stats:read',
            'trx:apply',
            'trx:cancel',
            'trx:commission',
            'user:read',
            'user_balance:read',
            'user_operations:read',
        ])))
    ))),

    /*
     * The Ameise page intentionally uses the common Ameise access model. Keep
     * remote mutations disabled until access to Ameise itself is protected.
     */
    'mutations_enabled' => (bool) env('AVITO_MUTATIONS_ENABLED', false),
    'mutation_confirmation' => env('AVITO_MUTATION_CONFIRMATION', 'AVITO'),

    'webhook_secret' => env('AVITO_WEBHOOK_SECRET'),
    'catalog_path' => resource_path('avito/api-catalog.json'),
    'catalog_url' => 'https://developers.avito.ru/web/1/openapi/list',
    'catalog_info_url' => 'https://developers.avito.ru/web/1/openapi/info/%s',
    'documentation_url' => 'https://developers.avito.ru/api-catalog',

    'allowed_hosts' => [
        'api.avito.ru',
        'pro.autoteka.ru',
    ],
    'timeout_seconds' => max(5, (int) env('AVITO_HTTP_TIMEOUT', 30)),
    'connect_timeout_seconds' => max(2, (int) env('AVITO_HTTP_CONNECT_TIMEOUT', 10)),
    'max_response_bytes' => max(65536, (int) env('AVITO_MAX_RESPONSE_BYTES', 5 * 1024 * 1024)),
    'log_retention_days' => max(1, (int) env('AVITO_LOG_RETENTION_DAYS', 90)),
];
