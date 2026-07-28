<?php

return [
    'currency_code' => env('LOGISTICS_CURRENCY_CODE', 'RUB'),
    'routing_driver' => env('LOGISTICS_ROUTING_DRIVER', 'valhalla'),
    'default_routing_profile' => env('LOGISTICS_DEFAULT_ROUTING_PROFILE', 'truck'),
    'matrix_max_cities_per_request' => (int) env('LOGISTICS_MATRIX_MAX_CITIES_PER_REQUEST', 50),
    'matrix_stale_days' => (int) env('LOGISTICS_MATRIX_STALE_DAYS', 30),
    'matrix_batch_cities' => (int) env('LOGISTICS_MATRIX_BATCH_CITIES', 10),
    'queue' => env('LOGISTICS_ROUTING_QUEUE', 'routing'),
    'queue_connection' => env('LOGISTICS_ROUTING_QUEUE_CONNECTION', 'redis-routing'),
    'lock_store' => env('LOGISTICS_ROUTING_LOCK_STORE'),
    'osm_data_version' => env('LOGISTICS_OSM_DATA_VERSION'),

    'valhalla' => [
        'engine_version' => env('VALHALLA_ENGINE_VERSION', '3.6.3'),
        'base_url' => env('VALHALLA_BASE_URL', 'http://valhalla:8002'),
        'connect_timeout' => (int) env('VALHALLA_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('VALHALLA_TIMEOUT', 30),
        'retry_times' => (int) env('VALHALLA_RETRY_TIMES', 2),
        'retry_delay_ms' => (int) env('VALHALLA_RETRY_DELAY_MS', 250),
    ],
];
