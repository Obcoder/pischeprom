<?php

return [
    'direct' => [
        'api_url' => env('YANDEX_DIRECT_API_URL', 'https://api.direct.yandex.com/json/v5'),
        'reports_url' => env('YANDEX_DIRECT_REPORTS_URL', 'https://api.direct.yandex.com/json/v5/reports'),
        'sandbox_api_url' => env('YANDEX_DIRECT_SANDBOX_API_URL'),
        'enable_real_send' => (bool) env('YANDEX_DIRECT_ENABLE_REAL_SEND', false),
        'timeout' => (int) env('YANDEX_DIRECT_TIMEOUT', 20),
        'auto_launch' => [
            'enabled' => (bool) env('YANDEX_DIRECT_AUTO_LAUNCH_ENABLED', true),
            'dry_run' => (bool) env('YANDEX_DIRECT_AUTO_LAUNCH_DRY_RUN', true),
            'daily_budget' => (float) env('YANDEX_DIRECT_AUTO_DAILY_BUDGET', 300),
            'max_daily_budget' => (float) env('YANDEX_DIRECT_AUTO_MAX_DAILY_BUDGET', 500),
            'region_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('YANDEX_DIRECT_AUTO_REGION_IDS', ''))))),
        ],
        'ai_autopilot' => [
            'enabled' => (bool) env('YANDEX_DIRECT_AI_AUTOPILOT_ENABLED', true),
            'mode' => env('YANDEX_DIRECT_AI_AUTOPILOT_MODE', 'monitor'),
            'full_auto_enabled' => (bool) env('YANDEX_DIRECT_AI_FULL_AUTO_ENABLED', false),
            'allow_auto_pause' => (bool) env('YANDEX_DIRECT_AI_ALLOW_AUTO_PAUSE', false),
            'cycle_minutes' => (int) env('YANDEX_DIRECT_AI_CYCLE_MINUTES', 60),
            'max_goods_per_cycle' => (int) env('YANDEX_DIRECT_AI_MAX_GOODS_PER_CYCLE', 50),
            'learning_window_days' => (int) env('YANDEX_DIRECT_AI_LEARNING_WINDOW_DAYS', 14),
            'min_learning_days' => (int) env('YANDEX_DIRECT_AI_MIN_LEARNING_DAYS', 3),
            'min_clicks_threshold' => (int) env('YANDEX_DIRECT_AI_MIN_CLICKS', 30),
            'min_impressions_threshold' => (int) env('YANDEX_DIRECT_AI_MIN_IMPRESSIONS', 1000),
            'cooldown_hours' => (int) env('YANDEX_DIRECT_AI_COOLDOWN_HOURS', 24),
            'auto_execute_min_confidence' => (int) env('YANDEX_DIRECT_AI_AUTO_EXECUTE_MIN_CONFIDENCE', 85),
            'target_cpl' => (float) env('YANDEX_DIRECT_AI_TARGET_CPL', 700),
            'waste_cost_limit' => (float) env('YANDEX_DIRECT_AI_WASTE_COST_LIMIT', 500),
            'low_ctr_threshold' => (float) env('YANDEX_DIRECT_AI_LOW_CTR', 0.6),
            'high_ctr_threshold' => (float) env('YANDEX_DIRECT_AI_HIGH_CTR', 2.5),
            'high_cpc_threshold' => (float) env('YANDEX_DIRECT_AI_HIGH_CPC', 80),
            'high_bounce_threshold' => (float) env('YANDEX_DIRECT_AI_HIGH_BOUNCE', 75),
            'max_daily_spend' => (float) env('YANDEX_DIRECT_AI_MAX_DAILY_SPEND', 1500),
            'max_budget_increase_percent' => (int) env('YANDEX_DIRECT_AI_MAX_BUDGET_INCREASE_PERCENT', 20),
        ],
    ],

    'oauth' => [
        'client_id' => env('YANDEX_OAUTH_CLIENT_ID'),
        'client_secret' => env('YANDEX_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('YANDEX_OAUTH_REDIRECT_URI'),
        'verification_redirect_uri' => env('YANDEX_OAUTH_VERIFICATION_REDIRECT_URI', 'https://oauth.yandex.ru/verification_code'),
        'authorize_url' => env('YANDEX_OAUTH_AUTHORIZE_URL', 'https://oauth.yandex.ru/authorize'),
        'token_url' => env('YANDEX_OAUTH_TOKEN_URL', 'https://oauth.yandex.ru/token'),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('YANDEX_OAUTH_SCOPES', ''))))),
    ],

    'metrica' => [
        'api_url' => env('YANDEX_METRICA_API_URL', 'https://api-metrika.yandex.net'),
        'counter_id' => env('YANDEX_METRICA_COUNTER_ID') ?: env('VITE_YANDEX_METRICA_COUNTER_ID'),
        'timeout' => (int) env('YANDEX_METRICA_TIMEOUT', 20),
    ],

    'default_utm_template' => env(
        'YANDEX_DIRECT_DEFAULT_UTM_TEMPLATE',
        'utm_source=yandex&utm_medium=cpc&utm_campaign=direct_goods&utm_content={ad_id}&utm_term={keyword}&utm_campaign_id={campaign_id}&utm_device={device_type}'
    ),

    'default_region_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('YANDEX_DIRECT_DEFAULT_REGION_IDS', ''))))),

    'default_minus_keywords' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'YANDEX_DIRECT_DEFAULT_MINUS_KEYWORDS',
        'рецепт,польза,вред,калорийность,фото,картинки,своими руками,как приготовить,дома,домашний,озон,wildberries,вайлдберриз,магнит,пятерочка,отзывы,форум,бесплатно'
    ))))),

    'limits' => [
        'title_1' => (int) env('YANDEX_DIRECT_TITLE_1_LIMIT', 56),
        'title_2' => (int) env('YANDEX_DIRECT_TITLE_2_LIMIT', 30),
        'text' => (int) env('YANDEX_DIRECT_TEXT_LIMIT', 81),
    ],
];
