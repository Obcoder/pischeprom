<?php

return [

    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', true),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),

        'bundle' => base_path('bootstrap/ssr/ssr.js'),

        'ensure_bundle_exists' => true,

        'ensure_runtime_exists' => false,

        'throw_on_error' => env('INERTIA_SSR_THROW_ON_ERROR', false),
    ],

    'testing' => [
        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/Pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
