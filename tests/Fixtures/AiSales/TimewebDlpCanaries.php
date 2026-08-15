<?php

namespace Tests\Fixtures\AiSales;

final class TimewebDlpCanaries
{
    public static function all(): array
    {
        return [
            'credentials' => [
                'fixture_id' => 'SYNTHETIC-CANARY-CREDENTIALS',
                'api_key' => 'sk-fake-stage05-never-send-000000000000',
                'jwt' => 'eyJmYWtlZmFrZWZha2U.eyJmYWtlZmFrZWZha2U.c3ludGhldGljZmFrZQ',
            ],
            'personal' => [
                'fixture_id' => 'SYNTHETIC-CANARY-PERSONAL',
                'email' => 'blocked@example.invalid',
                'phone' => '+7 (000) 111-22-33',
            ],
            'cross_lane' => [
                'fixture_id' => 'SYNTHETIC-CANARY-LANES',
                'supplier_secret_marker' => 'SYNTHETIC_SUPPLIER_SECRET',
                'customer_secret_marker' => 'SYNTHETIC_CUSTOMER_SECRET',
            ],
            'raw_correspondence' => [
                'fixture_id' => 'SYNTHETIC-CANARY-RAW',
                'raw_correspondence' => 'SYNTHETIC_RAW_CORRESPONDENCE',
            ],
            'unclassified' => [
                'fixture_id' => 'SYNTHETIC-CANARY-UNCLASSIFIED',
                'unclassified_field' => 'SYNTHETIC_UNCLASSIFIED_VALUE',
            ],
        ];
    }
}
