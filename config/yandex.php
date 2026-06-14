<?php

return [
    'direct' => [
        'api_url' => env('YANDEX_DIRECT_API_URL', 'https://api.direct.yandex.com/json/v5'),
        'reports_url' => env('YANDEX_DIRECT_REPORTS_URL', 'https://api.direct.yandex.com/json/v5/reports'),
        'sandbox_api_url' => env('YANDEX_DIRECT_SANDBOX_API_URL'),
        'enable_real_send' => (bool) env('YANDEX_DIRECT_ENABLE_REAL_SEND', false),
        'timeout' => (int) env('YANDEX_DIRECT_TIMEOUT', 20),
    ],

    'oauth' => [
        'client_id' => env('YANDEX_OAUTH_CLIENT_ID'),
        'client_secret' => env('YANDEX_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('YANDEX_OAUTH_REDIRECT_URI'),
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
