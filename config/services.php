<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Postmark, AWS, Unisender Go and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'email_provider' => env('EMAIL_PROVIDER', 'log'),

    'unisender_go' => [
        'enabled' => env('UNISENDER_GO_ENABLED', false),
        'api_base' => env('UNISENDER_GO_API_BASE', 'https://go1.unisender.ru/en/transactional/api/v1'),
        'api_key' => env('UNISENDER_GO_API_KEY'),
        'from_email' => env('UNISENDER_GO_FROM_EMAIL', 'com@food-server.ru'),
        'from_name' => env('UNISENDER_GO_FROM_NAME', 'Pischeprom'),
        'reply_to' => env('UNISENDER_GO_REPLY_TO', env('UNISENDER_GO_FROM_EMAIL', 'com@food-server.ru')),
        'track_read' => env('UNISENDER_GO_TRACK_READ', true),
        'track_links' => env('UNISENDER_GO_TRACK_LINKS', true),
        'global_language' => env('UNISENDER_GO_GLOBAL_LANGUAGE', 'ru'),
        'webhook_url' => env('UNISENDER_GO_WEBHOOK_URL'),
        'webhook_max_parallel' => env('UNISENDER_GO_WEBHOOK_MAX_PARALLEL', 10),
        'webhook_delivery_info' => env('UNISENDER_GO_WEBHOOK_DELIVERY_INFO', true),
        'timeout' => env('UNISENDER_GO_TIMEOUT', 20),
    ],

    'mailings' => [
        'batch_size' => env('MAILINGS_BATCH_SIZE', 500),
        'daily_limit' => env('MAILINGS_DAILY_LIMIT', 500),
        'hourly_limit' => env('MAILINGS_HOURLY_LIMIT', 100),
        'stop_on_spam_rate' => env('MAILINGS_STOP_ON_SPAM_RATE', 0.002),
        'stop_on_hard_bounce_rate' => env('MAILINGS_STOP_ON_HARD_BOUNCE_RATE', 0.05),
        'require_consent_for_mass' => env('MAILINGS_REQUIRE_CONSENT_FOR_MASS', true),
        'test_recipient' => env('MAILINGS_TEST_RECIPIENT'),
        'dry_run' => env('MAILINGS_DRY_RUN', false),
    ],

    'avito' => [
        'client_id' => env('AVITO_CLIENT_ID'),
        'client_secret' => env('AVITO_CLIENT_SECRET'),
        'api_url' => env('AVITO_API_URL', 'https://api.avito.ru/token'),
    ],

    'yandex_search' => [
        'api_key' => env('YANDEX_SEARCH_API_KEY'),
        'folder_id' => env('YANDEX_SEARCH_FOLDER_ID'),
        'region' => env('YANDEX_SEARCH_REGION', 'ru'),
        'host' => env('YANDEX_SEARCH_HOST', 'searchapi.api.cloud.yandex.net'),
    ],

    'wikipedia' => [
        'user_agent' => env('WIKIPEDIA_USER_AGENT', 'pischeprom/1.0'),
    ],

    'yandex_mail' => [
        'address' => env('MAILBOX_1_ADDRESS', env('YANDEX_MAIL_ADDRESS', env('MAIL_FROM_ADDRESS'))),

        'attachments_disk' => env('YANDEX_ATTACHMENTS_DISK', 'yandex'),

        'imap' => [
            'host' => env('MAILBOX_1_IMAP_HOST', env('YANDEX_IMAP_HOST', 'imap.yandex.com')),
            'port' => (int) env('MAILBOX_1_IMAP_PORT', env('YANDEX_IMAP_PORT', 993)),
            'encryption' => env('MAILBOX_1_IMAP_ENCRYPTION', env('YANDEX_IMAP_ENCRYPTION', 'ssl')),
            'username' => env('MAILBOX_1_IMAP_USERNAME', env('YANDEX_IMAP_USERNAME')),
            'password' => env('MAILBOX_1_IMAP_PASSWORD', env('YANDEX_IMAP_PASSWORD')),
            'inbox' => env('MAILBOX_1_IMAP_INBOX', env('YANDEX_IMAP_INBOX', 'INBOX')),
            'sent' => env('MAILBOX_1_IMAP_SENT', env('YANDEX_IMAP_SENT', 'Sent')),
        ],

        'folders' => array_values(array_filter(array_map(
                                                   'trim',
                                                   explode(',', env('MAILBOX_1_IMAP_FOLDERS', env('YANDEX_IMAP_FOLDERS', 'INBOX,Sent')))
                                               ))),

        'mailboxes' => [
            [
                'address' => env('MAILBOX_1_ADDRESS', env('YANDEX_MAIL_ADDRESS', env('MAIL_FROM_ADDRESS'))),
                'name' => env('MAILBOX_1_NAME', env('YANDEX_MAIL_NAME', env('MAIL_FROM_NAME'))),
                'from_name' => env('MAILBOX_1_FROM_NAME', env('MAILBOX_1_NAME', env('MAIL_FROM_NAME'))),
                'imap' => [
                    'host' => env('MAILBOX_1_IMAP_HOST', env('YANDEX_IMAP_HOST', 'imap.yandex.com')),
                    'port' => (int) env('MAILBOX_1_IMAP_PORT', env('YANDEX_IMAP_PORT', 993)),
                    'encryption' => env('MAILBOX_1_IMAP_ENCRYPTION', env('YANDEX_IMAP_ENCRYPTION', 'ssl')),
                    'username' => env('MAILBOX_1_IMAP_USERNAME', env('YANDEX_IMAP_USERNAME')),
                    'password' => env('MAILBOX_1_IMAP_PASSWORD', env('YANDEX_IMAP_PASSWORD')),
                    'inbox' => env('MAILBOX_1_IMAP_INBOX', env('YANDEX_IMAP_INBOX', 'INBOX')),
                    'sent' => env('MAILBOX_1_IMAP_SENT', env('YANDEX_IMAP_SENT', 'Sent')),
                ],
                'smtp' => [
                    'host' => env('MAILBOX_1_SMTP_HOST', env('YANDEX_SMTP_HOST', env('MAIL_HOST'))),
                    'port' => (int) env('MAILBOX_1_SMTP_PORT', env('YANDEX_SMTP_PORT', env('MAIL_PORT', 465))),
                    'encryption' => env('MAILBOX_1_SMTP_ENCRYPTION', env('YANDEX_SMTP_ENCRYPTION', env('MAIL_ENCRYPTION', 'ssl'))),
                    'username' => env('MAILBOX_1_SMTP_USERNAME', env('YANDEX_SMTP_USERNAME', env('MAIL_USERNAME'))),
                    'password' => env('MAILBOX_1_SMTP_PASSWORD', env('YANDEX_SMTP_PASSWORD', env('MAIL_PASSWORD'))),
                    'timeout' => (int) env('MAILBOX_1_SMTP_TIMEOUT', 30),
                ],
                'folders' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', env('MAILBOX_1_IMAP_FOLDERS', env('YANDEX_IMAP_FOLDERS', 'INBOX,Sent')))
                ))),
            ],
            [
                'address' => env('MAILBOX_2_ADDRESS', env('YANDEX_MAILBOX_2_ADDRESS')),
                'name' => env('MAILBOX_2_NAME', env('YANDEX_MAILBOX_2_NAME')),
                'from_name' => env('MAILBOX_2_FROM_NAME', env('MAILBOX_2_NAME', env('MAIL_FROM_NAME'))),
                'imap' => [
                    'host' => env('MAILBOX_2_IMAP_HOST', env('YANDEX_MAILBOX_2_IMAP_HOST', 'imap.beget.com')),
                    'port' => (int) env('MAILBOX_2_IMAP_PORT', env('YANDEX_MAILBOX_2_IMAP_PORT', 993)),
                    'encryption' => env('MAILBOX_2_IMAP_ENCRYPTION', env('YANDEX_MAILBOX_2_IMAP_ENCRYPTION', 'ssl')),
                    'username' => env('MAILBOX_2_IMAP_USERNAME', env('YANDEX_MAILBOX_2_IMAP_USERNAME')),
                    'password' => env('MAILBOX_2_IMAP_PASSWORD', env('YANDEX_MAILBOX_2_IMAP_PASSWORD')),
                    'inbox' => env('MAILBOX_2_IMAP_INBOX', env('YANDEX_MAILBOX_2_IMAP_INBOX', 'INBOX')),
                    'sent' => env('MAILBOX_2_IMAP_SENT', env('YANDEX_MAILBOX_2_IMAP_SENT', 'Sent')),
                ],
                'smtp' => [
                    'host' => env('MAILBOX_2_SMTP_HOST', env('YANDEX_MAILBOX_2_SMTP_HOST', 'smtp.beget.com')),
                    'port' => (int) env('MAILBOX_2_SMTP_PORT', env('YANDEX_MAILBOX_2_SMTP_PORT', 465)),
                    'encryption' => env('MAILBOX_2_SMTP_ENCRYPTION', env('YANDEX_MAILBOX_2_SMTP_ENCRYPTION', 'ssl')),
                    'username' => env('MAILBOX_2_SMTP_USERNAME', env('YANDEX_MAILBOX_2_SMTP_USERNAME')),
                    'password' => env('MAILBOX_2_SMTP_PASSWORD', env('YANDEX_MAILBOX_2_SMTP_PASSWORD')),
                    'timeout' => (int) env('MAILBOX_2_SMTP_TIMEOUT', 30),
                ],
                'folders' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', env('MAILBOX_2_IMAP_FOLDERS', env('YANDEX_MAILBOX_2_IMAP_FOLDERS', 'INBOX,Sent')))
                ))),
            ],
        ],
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],

    'yandex_metrica' => [
        'counter_id' => env('YANDEX_METRICA_COUNTER_ID') ?: env('VITE_YANDEX_METRICA_COUNTER_ID'),
    ],

    'dadata' => [
        'token' => env('DADATA_TOKEN'),
        'secret' => env('DADATA_SECRET'),
    ],

    'beeline_pbx' => [
        'api_token' => env('BEELINE_PBX_API_TOKEN'),
        'crm_token' => env('BEELINE_PBX_CRM_TOKEN', env('BEELINE_PBX_API_TOKEN')),
        'api_url' => env('BEELINE_PBX_API_URL', 'https://cloudpbx.beeline.ru/apis/portal'),
        'history_url' => env('BEELINE_PBX_HISTORY_URL') ?: 'https://cloudpbx.beeline.ru/crmapi/v1/history/json',
        'webhook_url' => env('BEELINE_PBX_WEBHOOK_URL'),
        'subscription_pattern' => env('BEELINE_PBX_SUBSCRIPTION_PATTERN'),
        'own_numbers' => array_filter(array_map('trim', explode(',', (string) env('BEELINE_PBX_OWN_NUMBERS', '79650160001')))),
        'click_to_call_abonent' => env('BEELINE_PBX_CLICK_TO_CALL_ABONENT'),
        'click_to_call_employee_phone' => env('BEELINE_PBX_CLICK_TO_CALL_EMPLOYEE_PHONE', '79650160001'),
    ],

    'orders' => [
        'manager_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ORDER_MANAGER_EMAILS', 'com@food-server.ru,office@180022.ru'))
        ))),
    ],

    'max' => [
        'api_url' => env('MAX_API_URL', 'https://platform-api2.max.ru'),
        'access_token' => env('MAX_ACCESS_TOKEN', env('MAX_BOT_TOKEN')),
        'ca_bundle' => env('MAX_CA_BUNDLE', base_path('certs/russian_trusted_ca_bundle.pem')),
        'ssl_verify' => filter_var(env('MAX_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'bot_url' => env('MAX_BOT_URL'),
        'bot_username' => env('MAX_BOT_USERNAME'),
        'invite_text' => env('MAX_INVITE_TEXT', 'Здравствуйте! Напишите нам в MAX: :url После первого сообщения менеджер сможет отвечать вам в этом чате.'),
        'webhook_secret' => env('MAX_WEBHOOK_SECRET'),
        'manager_chat_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('MAX_MANAGER_CHAT_IDS', ''))
        ))),
        'webhook_update_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('MAX_WEBHOOK_UPDATE_TYPES', 'bot_started,message_created,message_removed,message_edited,bot_added,bot_removed,user_added,user_removed,chat_title_changed,message_callback'))
        ))),
    ],

];
