<?php

namespace Tests\Feature\Banking;

use App\Jobs\Banking\SyncSberStatementsJob;
use App\Models\BankConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

class BankingCommandsTest extends BankingDatabaseTestCase
{
    /** @var array<int, string> */
    private array $temporarySecretFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarySecretFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

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

    public function test_sandbox_tokens_are_imported_from_protected_files_and_plaintext_sources_are_removed(): void
    {
        Http::fake();
        $owner = $this->createEntity();
        $administrator = $this->createUser();
        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'crm',
        ]);
        $administrator->assignRole($role);
        $clientSecretFile = $this->protectedSecretFile('client-secret-value');
        $accessTokenFile = $this->protectedSecretFile('sandbox-access-token-value');
        $refreshTokenFile = $this->protectedSecretFile('sandbox-refresh-token-value');
        config([
            'banking.sber.environment' => 'sandbox',
            'banking.sber.read_only' => true,
            'banking.sber.client_id' => 'sandbox-client-id',
            'banking.sber.client_secret_file' => $clientSecretFile,
            'banking.sber.scopes' => ['openid', 'GET_STATEMENT_ACCOUNT'],
        ]);

        $this->artisan('bank:sber:import-sandbox-tokens', [
            '--owner-entity' => $owner->id,
            '--connected-by' => $administrator->id,
            '--access-token-file' => $accessTokenFile,
            '--refresh-token-file' => $refreshTokenFile,
            '--access-expires-at' => now()->addDays(30)->toAtomString(),
            '--refresh-expires-at' => now()->addDays(180)->toAtomString(),
        ])
            ->doesntExpectOutputToContain('sandbox-access-token-value')
            ->doesntExpectOutputToContain('sandbox-refresh-token-value')
            ->doesntExpectOutputToContain($accessTokenFile)
            ->doesntExpectOutputToContain($refreshTokenFile)
            ->expectsOutput('No network request was made. Token values were not printed.')
            ->expectsOutput('Plaintext token source files were removed.')
            ->assertSuccessful();

        $connection = BankConnection::query()->sole();
        $this->assertSame('sandbox', $connection->environment->value);
        $this->assertSame('active', $connection->status->value);
        $this->assertSame('sandbox-access-token-value', $connection->access_token);
        $this->assertSame('sandbox-refresh-token-value', $connection->refresh_token);
        $this->assertSame(
            ['openid', 'GET_STATEMENT_ACCOUNT'],
            $connection->scopes
        );
        $this->assertNotSame(
            'sandbox-access-token-value',
            DB::table('bank_connections')->value('access_token')
        );
        $this->assertNotSame(
            'sandbox-refresh-token-value',
            DB::table('bank_connections')->value('refresh_token')
        );
        $this->assertDatabaseHas('bank_audit_events', [
            'action' => 'bank.connection.sandbox_tokens_imported',
            'user_id' => $administrator->id,
        ]);
        $this->assertFileDoesNotExist($accessTokenFile);
        $this->assertFileDoesNotExist($refreshTokenFile);
        $this->assertFileExists($clientSecretFile);
        Http::assertNothingSent();
    }

    public function test_sandbox_token_import_rejects_production_and_preserves_source_files(): void
    {
        $owner = $this->createEntity();
        $administrator = $this->createUser();
        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'crm',
        ]);
        $administrator->assignRole($role);
        $clientSecretFile = $this->protectedSecretFile('client-secret-value');
        $accessTokenFile = $this->protectedSecretFile('sandbox-access-token-value');
        $refreshTokenFile = $this->protectedSecretFile('sandbox-refresh-token-value');
        config([
            'banking.sber.environment' => 'production',
            'banking.sber.read_only' => true,
            'banking.sber.client_id' => 'production-client-id',
            'banking.sber.client_secret_file' => $clientSecretFile,
            'banking.sber.scopes' => ['openid', 'GET_STATEMENT_ACCOUNT'],
        ]);

        $this->artisan('bank:sber:import-sandbox-tokens', [
            '--owner-entity' => $owner->id,
            '--connected-by' => $administrator->id,
            '--access-token-file' => $accessTokenFile,
            '--refresh-token-file' => $refreshTokenFile,
            '--access-expires-at' => now()->addDays(30)->toAtomString(),
            '--refresh-expires-at' => now()->addDays(180)->toAtomString(),
        ])
            ->expectsOutput('Sandbox tokens can only be imported in the sandbox environment.')
            ->expectsOutput('No network request was made. Token values were not printed.')
            ->assertFailed();

        $this->assertDatabaseCount('bank_connections', 0);
        $this->assertFileExists($accessTokenFile);
        $this->assertFileExists($refreshTokenFile);
    }

    private function protectedSecretFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-sber-command-');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        chmod($path, 0600);
        $this->temporarySecretFiles[] = $path;

        return $path;
    }
}
