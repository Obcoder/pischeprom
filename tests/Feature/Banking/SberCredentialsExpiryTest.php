<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Services\SberHealthService;
use App\Jobs\Banking\CheckSberCredentialsExpiryJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

class SberCredentialsExpiryTest extends BankingDatabaseTestCase
{
    public function test_client_secret_warning_is_emitted_exactly_thirty_days_before_expiry(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 00:00:00');
        config(['banking.sber.client_secret_expires_at' => '2026-07-31']);

        try {
            $this->assertContains(
                'client_secret_expires_in_30_days',
                app(SberHealthService::class)->expiringReasons(),
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_refresh_token_warning_is_emitted_exactly_fourteen_days_before_expiry(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 00:00:00');
        Event::fake([BankConnectionRequiresAttention::class]);
        $connection = $this->createConnection([
            'refresh_token_expires_at' => '2026-07-15 00:00:00',
        ]);

        try {
            (new CheckSberCredentialsExpiryJob)->handle(app(SberHealthService::class));

            Event::assertDispatched(
                BankConnectionRequiresAttention::class,
                fn (BankConnectionRequiresAttention $event): bool => $event->connection->is($connection)
                    && $event->reason === 'refresh_token_expires_in_14_days',
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
