<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mirror production MySQL's Unicode LOWER for every SQLite test connection.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction(
                'lower',
                fn (?string $value): ?string => $value === null ? null : mb_strtolower($value, 'UTF-8'),
                1,
            );
        }

        config()->set('inertia.ssr.enabled', false);
        $this->withoutVite();
    }
}
