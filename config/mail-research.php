<?php

return [
    // Manual catalog research shares the already configured product-content provider.
    'enabled' => (bool) env('MAIL_RESEARCH_AI_ENABLED', env('GOODS_SEO_AI_ENABLED', false)),
    'api_key' => env('MAIL_RESEARCH_AI_API_KEY') ?: env('GOODS_SEO_AI_API_KEY') ?: env('AI_TIMEWEB_LOCAL_RU_API_KEY', ''),
    'model' => env('MAIL_RESEARCH_AI_MODEL', env('GOODS_SEO_AI_MODEL', '')),
    'token_parameter' => env('MAIL_RESEARCH_AI_TOKEN_PARAMETER', env('GOODS_SEO_AI_TOKEN_PARAMETER', 'max_tokens')),
];
