<?php

namespace Tests\Feature\Banking;

use App\Jobs\Banking\SyncSberStatementsJob;
use Illuminate\Support\Facades\Queue;

class BankingCommandsTest extends BankingDatabaseTestCase
{
    public function test_sync_command_makes_no_request_and_queues_nothing_when_api_is_disabled(): void
    {
        Queue::fake();
        config(['banking.sber.enabled' => false]);
        $connection = $this->createConnection();

        $this->artisan('bank:sber:sync', [
            '--connection' => $connection->id,
            '--incremental' => true,
        ])
            ->expectsOutput('Sber API is disabled; no request was made.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_sync_command_validates_dates_and_queues_read_only_job_by_default(): void
    {
        Queue::fake();
        $connection = $this->createConnection();

        $this->artisan('bank:sber:sync', [
            '--connection' => $connection->id,
            '--from' => '2026-07-20',
            '--to' => '2026-07-24',
            '--sandbox-smoke' => true,
        ])
            ->expectsOutput('Read-only synchronization was queued on the banking queue.')
            ->assertSuccessful();

        Queue::assertPushed(
            SyncSberStatementsJob::class,
            fn (SyncSberStatementsJob $job): bool => $job->mode === 'manual'
                && $job->from === '2026-07-20'
                && $job->to === '2026-07-24'
        );

        $this->artisan('bank:sber:sync', [
            '--connection' => $connection->id,
            '--from' => '2026-07-24',
            '--to' => '2026-07-20',
            '--sandbox-smoke' => true,
        ])->assertExitCode(2);
    }

    public function test_health_command_never_prints_secret_values_or_paths(): void
    {
        config([
            'banking.sber.client_id' => 'secret-client-id-value',
            'banking.sber.client_secret_file' => '/secret/path/client-secret',
            'banking.sber.mtls_key_path' => '/secret/path/private-key',
        ]);

        $this->artisan('bank:sber:health')
            ->doesntExpectOutputToContain('secret-client-id-value')
            ->doesntExpectOutputToContain('/secret/path')
            ->expectsOutput('No network request was made. No secret value or secret-file path was printed.')
            ->assertFailed();
    }

    public function test_deploy_health_check_safely_skips_a_disabled_integration(): void
    {
        config([
            'banking.enabled' => false,
            'banking.sber.enabled' => false,
        ]);

        $this->artisan('bank:sber:health', ['--if-enabled' => true])
            ->expectsOutput('Sber health check skipped because the integration is disabled.')
            ->expectsOutput('No network request was made. No secret value or secret-file path was printed.')
            ->assertSuccessful();
    }
}
