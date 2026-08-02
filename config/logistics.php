<?php

return [
    'authorization_enabled' => filter_var(
        env('LOGISTICS_AUTHORIZATION_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),
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

    'map' => [
        'enabled' => filter_var(env('LOGISTICS_MAP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'coverage' => 'Russia',
        'style_version' => env('LOGISTICS_MAP_STYLE_VERSION', '1'),
        'style_url' => env('LOGISTICS_MAP_STYLE_URL', '/api/logistics/map/style'),
        'pmtiles_url' => env('LOGISTICS_MAP_PMTILES_URL', '/maps/logistics/russia.pmtiles'),
        'glyphs_url' => env('LOGISTICS_MAP_GLYPHS_URL', '/maps/logistics/fonts/{fontstack}/{range}.pbf'),
        'sprite_url' => env('LOGISTICS_MAP_SPRITE_URL', '/maps/logistics/sprites/basic'),
        'asset_origins' => array_values(array_filter(array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', (string) env('LOGISTICS_MAP_ASSET_ORIGINS', '')),
        ))),
        'attribution' => '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a> · © <a href="https://openmaptiles.org/">OpenMapTiles</a>',
        'default_center' => [94.0, 66.0],
        'default_zoom' => (float) env('LOGISTICS_MAP_DEFAULT_ZOOM', 2.3),
        'max_features' => (int) env('LOGISTICS_MAP_MAX_FEATURES', 1000),
        'max_trips' => (int) env('LOGISTICS_MAP_MAX_TRIPS', 100),
        'max_selected_trips' => (int) env('LOGISTICS_MAP_MAX_SELECTED_TRIPS', 20),
        'matrix_preview_ttl' => (int) env('LOGISTICS_MAP_MATRIX_PREVIEW_TTL', 21600),
        'release_manifest_path' => env(
            'LOGISTICS_GIS_RELEASE_MANIFEST',
            '/srv/pischeprom-gis-state/current/release-manifest.json'
        ),
        'preflight_status_path' => env(
            'LOGISTICS_GIS_PREFLIGHT_STATUS',
            '/srv/pischeprom-gis-state/current/last-preflight.json'
        ),
        'range_status_path' => env(
            'LOGISTICS_GIS_RANGE_STATUS',
            '/srv/pischeprom-gis-state/current/last-range-check.json'
        ),
        'activation_status_path' => env(
            'LOGISTICS_GIS_ACTIVATION_STATUS',
            '/srv/pischeprom-gis-state/current/last-activation.json'
        ),
        'production_smoke_status_path' => env(
            'LOGISTICS_GIS_PRODUCTION_SMOKE_STATUS',
            '/srv/pischeprom-gis-state/current/last-production-smoke.json'
        ),
        'publication_status_path' => env(
            'LOGISTICS_GIS_MAP_PUBLICATION_STATUS',
            '/srv/pischeprom-gis-state/current/last-map-publication.json'
        ),
    ],

    'valhalla' => [
        'engine_version' => env('VALHALLA_ENGINE_VERSION', '3.6.3'),
        'base_url' => env('VALHALLA_BASE_URL', 'http://valhalla:8002'),
        'connect_timeout' => (int) env('VALHALLA_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('VALHALLA_TIMEOUT', 30),
        'retry_times' => (int) env('VALHALLA_RETRY_TIMES', 2),
        'retry_delay_ms' => (int) env('VALHALLA_RETRY_DELAY_MS', 250),
    ],
];
