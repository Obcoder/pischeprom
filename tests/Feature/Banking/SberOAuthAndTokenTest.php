<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\DTO\BankTokensData;
use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankUnavailableException;
use App\Domain\Banking\Exceptions\ReadOnlyViolationException;
use App\Domain\Banking\Providers\Sber\SberIdTokenValidator;
use App\Domain\Banking\Providers\Sber\SberOAuthService;
use App\Domain\Banking\Providers\Sber\SberReadOnlyApiClient;
use App\Domain\Banking\Providers\Sber\SberTokenManager;
use App\Domain\Banking\Providers\Sber\SecretFileReader;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;

class SberOAuthAndTokenTest extends BankingDatabaseTestCase
{
    private string $clientSecretFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientSecretFile = tempnam(sys_get_temp_dir(), 'sber-client-secret-');
        file_put_contents($this->clientSecretFile, 'test-secret');
        chmod($this->clientSecretFile, 0600);
        config([
            'banking.sber.environment' => 'sandbox',
            'banking.sber.client_id' => 'client-id',
            'banking.sber.client_secret_file' => $this->clientSecretFile,
            'banking.sber.redirect_uri' => 'https://app.example.test/banking/sber/oauth/callback',
            'banking.sber.scopes' => ['openid', 'GET_STATEMENT_ACCOUNT'],
            'banking.sber.environments.sandbox.authorization_base_url' => 'https://sber-auth.example.test',
            'banking.sber.environments.sandbox.authorization_hosts' => ['sber-auth.example.test'],
            'banking.sber.allowed_hosts' => ['sber-auth.example.test'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->clientSecretFile);

        parent::tearDown();
    }

    public function test_oauth_state_is_hashed_short_lived_and_single_use(): void
    {
        $user = $this->createUser();
        $owner = $this->createEntity();
        $client = Mockery::mock(SberReadOnlyApiClient::class);
        $client->shouldReceive('validatedBaseUrl')
            ->once()
            ->andReturn('https://sber-auth.example.test');
        $client->shouldReceive('request')
            ->once()
            ->withArgs(fn ($environment, $alias, $query, $form): bool => $alias === 'oauth.token'
                && $form['grant_type'] === 'authorization_code'
                && $form['code'] === 'authorization-code'
                && $form['client_secret'] === 'test-secret')
            ->andReturn([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => '3600',
                'refresh_expires_in' => (string) (180 * 86400),
                'scope' => 'openid GET_STATEMENT_ACCOUNT',
            ]);
        $idTokens = Mockery::mock(SberIdTokenValidator::class);
        $idTokens->shouldNotReceive('validate');
        $service = new SberOAuthService(
            $client,
            new SecretFileReader,
            $idTokens,
            app(BankAuditLogger::class),
        );

        $url = $service->authorizationUrl($user, $owner);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $state = $query['state'];
        $this->assertNotEmpty($state);
        $this->assertNotSame($state, DB::table('bank_oauth_attempts')->value('state_hash'));
        $this->assertSame(hash('sha256', $state), DB::table('bank_oauth_attempts')->value('state_hash'));
        $this->assertNotNull(DB::table('bank_oauth_attempts')->value('nonce_hash'));

        $connection = $service->exchangeAuthorizationCode('authorization-code', $state);

        $this->assertSame($owner->id, $connection->owner_entity_id);
        $this->assertSame('active', $connection->status->value);
        $this->assertSame('access-token-value', $connection->access_token);
        $this->assertNotSame(
            'access-token-value',
            DB::table('bank_connections')->where('id', $connection->id)->value('access_token')
        );
        $this->assertNotNull(DB::table('bank_oauth_attempts')->value('consumed_at'));

        $this->expectException(BankAuthenticationException::class);

        $service->exchangeAuthorizationCode('authorization-code', $state);
    }

    public function test_payment_scope_is_rejected_before_oauth_attempt_is_created(): void
    {
        config(['banking.sber.scopes' => ['openid', 'GET_STATEMENT_ACCOUNT', 'PAY_DOC_RU']]);
        $service = app(SberOAuthService::class);

        $this->expectException(ReadOnlyViolationException::class);

        try {
            $service->authorizationUrl($this->createUser());
        } finally {
            $this->assertDatabaseCount('bank_oauth_attempts', 0);
        }
    }

    public function test_unrequested_payment_scope_in_token_response_is_rejected(): void
    {
        $client = Mockery::mock(SberReadOnlyApiClient::class);
        $client->shouldReceive('validatedBaseUrl')
            ->once()
            ->andReturn('https://sber-auth.example.test');
        $client->shouldReceive('request')
            ->once()
            ->andReturn([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => '3600',
                'refresh_expires_in' => (string) (180 * 86400),
                'scope' => 'openid GET_STATEMENT_ACCOUNT PAY_DOC_RU',
            ]);
        $idTokens = Mockery::mock(SberIdTokenValidator::class);
        $idTokens->shouldNotReceive('validate');
        $service = new SberOAuthService(
            $client,
            new SecretFileReader,
            $idTokens,
            app(BankAuditLogger::class),
        );
        parse_str(
            (string) parse_url(
                $service->authorizationUrl($this->createUser()),
                PHP_URL_QUERY
            ),
            $query
        );

        $this->expectException(ReadOnlyViolationException::class);

        $service->exchangeAuthorizationCode('authorization-code', $query['state']);
    }

    public function test_concurrent_refresh_observation_reuses_token_saved_by_another_worker(): void
    {
        $connection = $this->createConnection([
            'access_token' => 'old-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->addMonths(6),
        ]);
        $staleConnection = BankConnection::query()->findOrFail($connection->id);
        $connection->forceFill([
            'access_token' => 'token-from-other-worker',
            'access_token_expires_at' => now()->addHour(),
        ])->save();
        $oauth = Mockery::mock(SberOAuthService::class);
        $oauth->shouldNotReceive('refresh');
        $manager = new SberTokenManager($oauth, app(BankAuditLogger::class));

        $result = $manager->refreshAfterUnauthorized($staleConnection, 'old-token');

        $this->assertSame('token-from-other-worker', $result);
        $this->assertSame('refresh-token', $connection->fresh()->refresh_token);
    }

    public function test_refresh_saves_new_token_pair_atomically(): void
    {
        $connection = $this->createConnection([
            'access_token' => 'old-token',
            'refresh_token' => 'old-refresh',
            'access_token_expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->addMonth(),
        ]);
        $tokens = new BankTokensData(
            accessToken: 'new-token',
            refreshToken: 'new-refresh',
            accessTokenExpiresAt: CarbonImmutable::now()->addHour(),
            refreshTokenExpiresAt: CarbonImmutable::now()->addDays(180),
            scopes: ['openid', 'GET_STATEMENT_ACCOUNT'],
        );
        $oauth = Mockery::mock(SberOAuthService::class);
        $oauth->shouldReceive('refresh')->once()->andReturn($tokens);
        $manager = new SberTokenManager($oauth, app(BankAuditLogger::class));

        $this->assertSame(
            'new-token',
            $manager->refreshAfterUnauthorized($connection, 'old-token')
        );
        $connection->refresh();
        $this->assertSame('new-token', $connection->access_token);
        $this->assertSame('new-refresh', $connection->refresh_token);
    }

    public function test_transient_refresh_failure_does_not_require_new_oauth_consent(): void
    {
        $connection = $this->createConnection([
            'status' => 'active',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->addMonth(),
        ]);
        $oauth = Mockery::mock(SberOAuthService::class);
        $oauth->shouldReceive('refresh')
            ->once()
            ->andThrow(new BankUnavailableException);
        $manager = new SberTokenManager($oauth, app(BankAuditLogger::class));

        try {
            $manager->refreshAfterUnauthorized($connection, 'expired-token');
            $this->fail('A transient Sber failure must be propagated to the retrying job.');
        } catch (BankUnavailableException) {
            $this->assertSame('error', $connection->fresh()->status->value);
            $this->assertNotSame(
                'reauthorization_required',
                $connection->fresh()->status->value
            );
        }
    }
}
