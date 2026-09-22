<?php

return [
    // Reuse the catalog AI connection unless translations have their own settings.
    'enabled' => (bool) env('PRODUCT_TRANSLATIONS_AI_ENABLED', env('GOODS_SEO_AI_ENABLED', false)),

    'timeweb' => [
        'api_key' => env('PRODUCT_TRANSLATIONS_AI_API_KEY') ?: env('GOODS_SEO_AI_API_KEY') ?: env('AI_TIMEWEB_LOCAL_RU_API_KEY', ''),
        'model' => env('PRODUCT_TRANSLATIONS_AI_MODEL', env('GOODS_SEO_AI_MODEL', '')),
        'token_parameter' => env('PRODUCT_TRANSLATIONS_AI_TOKEN_PARAMETER', env('GOODS_SEO_AI_TOKEN_PARAMETER', 'max_tokens')),
        'timeout_seconds' => (int) env('PRODUCT_TRANSLATIONS_AI_TIMEOUT_SECONDS', env('GOODS_SEO_AI_TIMEOUT_SECONDS', 45)),
    ],
];
