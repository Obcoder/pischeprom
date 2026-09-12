<?php

return [
    // Product SEO drafts are independent of the synthetic-only AI Sales transport.
    'enabled' => (bool) env('GOODS_SEO_AI_ENABLED', false),

    'timeweb' => [
        'api_key' => env('GOODS_SEO_AI_API_KEY') ?: env('AI_TIMEWEB_LOCAL_RU_API_KEY', ''),
        'model' => env('GOODS_SEO_AI_MODEL', ''),
        'token_parameter' => env('GOODS_SEO_AI_TOKEN_PARAMETER', 'max_tokens'),
        'timeout_seconds' => (int) env('GOODS_SEO_AI_TIMEOUT_SECONDS', 45),
    ],
];
