<?php

use App\Domain\Banking\Providers\Sber\SberBankProvider;

return [
    'enabled' => env('BANKING_ENABLED', false),

    'provider' => env('BANKING_PROVIDER', 'sber'),

    'bank_timezone' => env('BANKING_TIMEZONE', 'Europe/Moscow'),

    'queue' => env('BANKING_QUEUE', 'banking'),

    'queue_connection' => env('BANKING_QUEUE_CONNECTION', 'redis'),

    'lock_store' => env('BANKING_LOCK_STORE', 'redis'),

    'unidentified_notification_amount' => env('BANKING_UNIDENTIFIED_NOTIFICATION_AMOUNT', '100000.00'),

    'providers' => [
        'sber' => SberBankProvider::class,
    ],

    'sber' => [
        'enabled' => env('SBER_API_ENABLED', false),
        'read_only' => env('SBER_READ_ONLY', true),
        'environment' => env('SBER_ENVIRONMENT', 'sandbox'),
        'client_id' => env('SBER_CLIENT_ID'),
        'client_secret_file' => env('SBER_CLIENT_SECRET_FILE'),
        'client_secret_expires_at' => env('SBER_CLIENT_SECRET_EXPIRES_AT'),
        'redirect_uri' => env('SBER_REDIRECT_URI'),
        'mtls_cert_path' => env('SBER_MTLS_CERT_PATH'),
        'mtls_key_path' => env('SBER_MTLS_KEY_PATH'),
        'mtls_key_password_file' => env('SBER_MTLS_KEY_PASSWORD_FILE'),
        'ca_bundle_path' => env('SBER_CA_BUNDLE_PATH'),
        'jwt_public_key_path' => env('SBER_JWT_PUBLIC_KEY_PATH'),
        'jwt_issuer' => env('SBER_JWT_ISSUER'),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SBER_ALLOWED_HOSTS',
                'efs-sbbol-ift-web.testsbi.sberbank.ru,iftfintech.testsbi.sberbank.ru,sbi.sberbank.ru,fintech.sberbank.ru'
            ))
        ))),
        'request_timeout' => (int) env('SBER_REQUEST_TIMEOUT_SECONDS', 30),
        'connect_timeout' => (int) env('SBER_CONNECT_TIMEOUT_SECONDS', 10),
        'oauth_state_ttl_minutes' => (int) env('SBER_OAUTH_STATE_TTL_MINUTES', 10),
        'token_refresh_leeway_seconds' => (int) env('SBER_TOKEN_REFRESH_LEEWAY_SECONDS', 300),
        'sync_interval_minutes' => (int) env('SBER_SYNC_INTERVAL_MINUTES', 15),
        'initial_import_days' => (int) env('SBER_INITIAL_IMPORT_DAYS', 90),
        'control_sync_days' => (int) env('SBER_CONTROL_SYNC_DAYS', 3),
        'incremental_overlap_seconds' => (int) env('SBER_INCREMENTAL_OVERLAP_SECONDS', 120),
        'auto_match_enabled' => env('SBER_AUTO_MATCH_ENABLED', true),
        'auto_match_threshold' => (int) env('SBER_AUTO_MATCH_THRESHOLD', 90),
        'scopes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SBER_SCOPES',
                'openid,GET_STATEMENT_ACCOUNT'
            ))
        ))),

        /*
         * Hosts are fixed here deliberately. A value received from the bank is
         * never trusted as a pagination target unless it resolves to the active
         * environment's API host and an allowlisted path.
         */
        'environments' => [
            'sandbox' => [
                'authorization_base_url' => env(
                    'SBER_SANDBOX_AUTH_BASE_URL',
                    'https://efs-sbbol-ift-web.testsbi.sberbank.ru:9443'
                ),
                'api_base_url' => env(
                    'SBER_SANDBOX_API_BASE_URL',
                    'https://iftfintech.testsbi.sberbank.ru:9443'
                ),
                'authorization_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env(
                        'SBER_SANDBOX_ALLOWED_AUTH_HOSTS',
                        'efs-sbbol-ift-web.testsbi.sberbank.ru'
                    ))
                ))),
                'api_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env(
                        'SBER_SANDBOX_ALLOWED_API_HOSTS',
                        'iftfintech.testsbi.sberbank.ru'
                    ))
                ))),
            ],
            'production' => [
                'authorization_base_url' => env(
                    'SBER_PRODUCTION_AUTH_BASE_URL',
                    'https://sbi.sberbank.ru:9443'
                ),
                'api_base_url' => env(
                    'SBER_PRODUCTION_API_BASE_URL',
                    'https://fintech.sberbank.ru:9443'
                ),
                'authorization_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env(
                        'SBER_PRODUCTION_ALLOWED_AUTH_HOSTS',
                        'sbi.sberbank.ru'
                    ))
                ))),
                'api_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env(
                        'SBER_PRODUCTION_ALLOWED_API_HOSTS',
                        'fintech.sberbank.ru'
                    ))
                ))),
            ],
        ],
    ],
];
