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

    // Ameise currently exposes Avito with the same open access model as its
    // other modules. Remote actions are therefore enabled without an extra
    // page-specific authorization layer, as explicitly required by the owner.
    'mutations_enabled' => (bool) env('AVITO_MUTATIONS_ENABLED', true),
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

    'autoload' => [
        'media_disk' => env('AVITO_AUTOLOAD_MEDIA_DISK', 'avito'),
        'feed_name' => env('AVITO_AUTOLOAD_FEED_NAME', 'ameise-goods'),
        'max_images' => min(10, max(1, (int) env('AVITO_AUTOLOAD_MAX_IMAGES', 10))),
        'upload_interval_minutes' => max(60, (int) env('AVITO_AUTOLOAD_UPLOAD_INTERVAL_MINUTES', 60)),
    ],

    'messenger' => [
        'archive_disk' => env('AVITO_MESSENGER_ARCHIVE_DISK', 'avito'),
        'sync_interval_minutes' => min(59, max(1, (int) env('AVITO_MESSENGER_SYNC_INTERVAL', 5))),
        'chat_page_size' => min(100, max(1, (int) env('AVITO_MESSENGER_CHAT_PAGE_SIZE', 100))),
        'message_page_size' => min(100, max(1, (int) env('AVITO_MESSENGER_MESSAGE_PAGE_SIZE', 100))),
        'incremental_chat_limit' => min(1100, max(1, (int) env('AVITO_MESSENGER_INCREMENTAL_CHAT_LIMIT', 100))),
        'full_chat_limit' => min(1100, max(1, (int) env('AVITO_MESSENGER_FULL_CHAT_LIMIT', 1100))),
        'message_limit_per_chat' => min(1100, max(1, (int) env('AVITO_MESSENGER_MESSAGE_LIMIT_PER_CHAT', 1100))),
        'max_attachment_bytes' => max(1024 * 1024, (int) env('AVITO_MESSENGER_MAX_ATTACHMENT_BYTES', 25 * 1024 * 1024)),
    ],
];
