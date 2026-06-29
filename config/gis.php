<?php

return [
    'default_provider' => env('GIS_DEFAULT_PROVIDER', '2gis'),
    'request_timeout' => (int) env('GIS_REQUEST_TIMEOUT', 10),

    'map' => [
        'default_center' => [
            'lat' => (float) env('GIS_DEFAULT_LAT', 55.751244),
            'lon' => (float) env('GIS_DEFAULT_LON', 37.618423),
        ],
        'default_zoom' => (int) env('GIS_DEFAULT_ZOOM', 5),
    ],

    'providers' => [
        '2gis' => [
            'api_key' => env('TWOGIS_API_KEY'),
            'geocode_url' => env('TWOGIS_GEOCODE_URL', 'https://catalog.api.2gis.com/3.0/items/geocode'),
            'map_script_url' => env('TWOGIS_MAP_SCRIPT_URL', 'https://mapgl.2gis.com/api/js/v1'),
        ],

        'yandex' => [
            'api_key' => env('YANDEX_MAPS_API_KEY'),
            'geocode_url' => env('YANDEX_GEOCODE_URL', 'https://geocode-maps.yandex.ru/1.x/'),
            'map_script_url' => env('YANDEX_MAP_SCRIPT_URL', 'https://api-maps.yandex.ru/2.1/'),
        ],
    ],
];
