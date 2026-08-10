<?php

return [
    // Temporary public mode until authorization is enabled for the whole Ameise area.
    'authorization_enabled' => filter_var(
        env('AI_PRICE_LIST_AUTHORIZATION_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),
    // Enable only after the database, dedicated worker, scanner and provider
    // credentials have passed the production preflight.
    'enabled' => (bool) env('AI_PRICE_LISTS_ENABLED', false),
    'auto_apply' => false,
    'queue_connection' => env('AI_PRICE_LIST_QUEUE_CONNECTION', 'redis'),
    'queue' => env('AI_PRICE_LIST_QUEUE', 'price-lists'),
    'mail_ingestion' => [
        'queue_connection' => env('AI_PRICE_LIST_MAIL_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue' => env('AI_PRICE_LIST_MAIL_QUEUE', 'mail-sync'),
    ],
    'storage_disk' => env('AI_PRICE_LIST_STORAGE_DISK', 'yandex'),
    'storage_prefix' => trim(env('AI_PRICE_LIST_STORAGE_PREFIX', 'supplier-price-lists'), '/'),
    'retention_days' => (int) env('PRICE_LIST_RETENTION_DAYS', 730),

    'limits' => [
        'max_file_bytes' => (int) env('PRICE_LIST_MAX_FILE_MB', 25) * 1024 * 1024,
        'max_pages' => (int) env('PRICE_LIST_MAX_PAGES', 50),
        'max_ocr_pages' => (int) env('PRICE_LIST_MAX_OCR_PAGES', 30),
        'max_ocr_file_bytes' => (int) env('PRICE_LIST_MAX_OCR_FILE_MB', 10) * 1024 * 1024,
        'max_image_pixels' => (int) env('PRICE_LIST_MAX_IMAGE_MEGAPIXELS', 20) * 1_000_000,
        'max_sheets' => (int) env('PRICE_LIST_MAX_SHEETS', 20),
        'max_rows' => (int) env('PRICE_LIST_MAX_ROWS', 20000),
        'max_columns' => (int) env('PRICE_LIST_MAX_COLUMNS', 100),
        'max_zip_entries' => (int) env('PRICE_LIST_MAX_ZIP_ENTRIES', 5000),
        'max_uncompressed_bytes' => (int) env('PRICE_LIST_MAX_UNCOMPRESSED_MB', 200) * 1024 * 1024,
        'max_compression_ratio' => (int) env('PRICE_LIST_MAX_COMPRESSION_RATIO', 100),
        'timeout_seconds' => (int) env('AI_PRICE_LIST_TIMEOUT_SECONDS', 120),
        'max_attempts' => (int) env('AI_PRICE_LIST_MAX_ATTEMPTS', 4),
    ],

    'matching' => [
        'exact_threshold' => (float) env('PRICE_LIST_EXACT_MATCH_THRESHOLD', 0.96),
        'probable_threshold' => (float) env('PRICE_LIST_PROBABLE_MATCH_THRESHOLD', 0.70),
        'max_candidates' => (int) env('PRICE_LIST_MAX_CANDIDATES', 8),
        'price_change_warning_percent' => (float) env('PRICE_LIST_PRICE_CHANGE_WARNING_PERCENT', 25),
        'ai_reranking_enabled' => (bool) env('PRICE_LIST_AI_RERANKING_ENABLED', true),
        'ai_rerank_chunk_size' => (int) env('PRICE_LIST_AI_RERANK_CHUNK_SIZE', 20),
    ],

    'scanner' => env('PRICE_LIST_FILE_SCANNER', 'null'),
    'clamav_socket' => env('PRICE_LIST_CLAMAV_SOCKET'),
    'clamdscan_binary' => env('PRICE_LIST_CLAMDSCAN_BINARY', 'clamdscan'),
    'clamd_config' => env('PRICE_LIST_CLAMD_CONFIG', '/etc/clamav/clamd.conf'),
    'clamdscan_timeout_seconds' => (int) env('PRICE_LIST_CLAMDSCAN_TIMEOUT_SECONDS', 120),

    'ai' => [
        'enabled' => filter_var(env('AI_PRICE_LIST_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'provider' => env('AI_PROVIDER', 'yandex'),
        'model' => env('AI_PRICE_LIST_MODEL', 'yandexgpt-5.1'),
        'base_url' => rtrim(env('YANDEX_AI_BASE_URL', 'https://ai.api.cloud.yandex.net/v1'), '/'),
        'api_key' => env('YANDEX_AI_API_KEY'),
        'folder_id' => env('YANDEX_CLOUD_FOLDER_ID'),
        'data_logging' => false,
        'prompt_version' => 'price-list-v1',
        'schema_version' => 'price-list-v1',
        'daily_token_limit' => (int) env('AI_PRICE_LIST_DAILY_TOKEN_LIMIT', 500000),
        'monthly_token_limit' => (int) env('AI_PRICE_LIST_MONTHLY_TOKEN_LIMIT', 5000000),
        'daily_ocr_page_limit' => (int) env('AI_PRICE_LIST_DAILY_OCR_PAGE_LIMIT', 500),
        'monthly_ocr_page_limit' => (int) env('AI_PRICE_LIST_MONTHLY_OCR_PAGE_LIMIT', 5000),
        'requests_per_minute' => (int) env('AI_PRICE_LIST_REQUESTS_PER_MINUTE', 30),
        'max_rows_per_chunk' => (int) env('AI_PRICE_LIST_MAX_ROWS_PER_CHUNK', 20),
        'classification_min_confidence' => (float) env('AI_PRICE_LIST_CLASSIFICATION_MIN_CONFIDENCE', 0.90),
        'estimated_cost_per_1000_tokens' => env('AI_PRICE_LIST_ESTIMATED_COST_PER_1000_TOKENS'),
        'cost_currency' => env('AI_PRICE_LIST_COST_CURRENCY', 'RUB'),
    ],

    'ocr' => [
        'endpoint' => env('YANDEX_VISION_OCR_ENDPOINT', 'https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText'),
        'model' => env('YANDEX_VISION_OCR_MODEL', 'table'),
        'tiff2pdf_binary' => env('PRICE_LIST_TIFF2PDF_BINARY', 'tiff2pdf'),
        'language_codes' => array_values(array_filter(array_map('trim', explode(',', env('YANDEX_VISION_OCR_LANGUAGES', 'ru,en'))))),
    ],

    'max' => [
        'allowed_download_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', env('AI_PRICE_LIST_MAX_ALLOWED_HOSTS', 'max.ru,oneme.ru')),
        ))),
        'download_timeout_seconds' => (int) env('AI_PRICE_LIST_MAX_DOWNLOAD_TIMEOUT', 30),
        'max_redirects' => (int) env('AI_PRICE_LIST_MAX_DOWNLOAD_REDIRECTS', 2),
        'send_acknowledgement' => (bool) env('AI_PRICE_LIST_MAX_ACK_ENABLED', true),
        'max_attachments_per_message' => (int) env('AI_PRICE_LIST_MAX_ATTACHMENTS_PER_MESSAGE', 10),
    ],

    'recovery' => [
        'stale_after_minutes' => (int) env('AI_PRICE_LIST_STALE_AFTER_MINUTES', 20),
        'max_recoveries' => (int) env('AI_PRICE_LIST_MAX_RECOVERIES', 3),
    ],
];
