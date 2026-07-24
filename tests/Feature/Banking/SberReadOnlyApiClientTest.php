<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Exceptions\BankAuthorizationException;
use App\Domain\Banking\Exceptions\BankConfigurationException;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Domain\Banking\Exceptions\BankNetworkTimeoutException;
use App\Domain\Banking\Exceptions\BankRateLimitException;
use App\Domain\Banking\Exceptions\BankUnavailableException;
use App\Domain\Banking\Exceptions\BankValidationException;
use App\Domain\Banking\Exceptions\ReadOnlyViolationException;
use App\Domain\Banking\Providers\Sber\SberReadOnlyApiClient;
use App\Domain\Banking\Providers\Sber\SberResponseNormalizer;
use App\Domain\Banking\Providers\Sber\SberStatementService;
use App\Domain\Banking\Providers\Sber\SberTokenManager;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class SberReadOnlyApiClientTest extends TestCase
{
    private string $certificate;

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->certificate = tempnam(sys_get_temp_dir(), 'sber-cert-');
        $this->privateKey = tempnam(sys_get_temp_dir(), 'sber-key-');
        file_put_contents($this->certificate, 'test certificate');
        file_put_contents($this->privateKey, 'test private key');
        chmod($this->privateKey, 0600);

        config([
            'banking.enabled' => true,
            'banking.sber.enabled' => true,
            'banking.sber.read_only' => true,
            'banking.sber.mtls_cert_path' => $this->certificate,
            'banking.sber.mtls_key_path' => $this->privateKey,
            'banking.sber.mtls_key_password_file' => null,
            'banking.sber.ca_bundle_path' => null,
            'banking.sber.environments.sandbox.authorization_base_url' => 'https://sber-auth.example.test',
            'banking.sber.environments.sandbox.api_base_url' => 'https://sber-api.example.test',
            'banking.sber.environments.sandbox.authorization_hosts' => ['sber-auth.example.test'],
            'banking.sber.environments.sandbox.api_hosts' => ['sber-api.example.test'],
            'banking.sber.allowed_hosts' => ['sber-auth.example.test', 'sber-api.example.test'],
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->certificate);
        @unlink($this->privateKey);

        parent::tearDown();
    }

    public function test_public_provider_contract_has_no_payment_or_signature_method(): void
    {
        $methods = collect((new ReflectionClass(BankProviderInterface::class))->getMethods())
            ->pluck('name')
            ->map('strtolower')
            ->all();

        foreach ($methods as $method) {
            $this->assertStringNotContainsString('payment', $method);
            $this->assertStringNotContainsString('sign', $method);
            $this->assertStringNotContainsString('send', $method);
            $this->assertStringNotContainsString('execute', $method);
        }
    }

    public function test_payment_endpoint_and_unknown_alias_are_rejected_before_http(): void
    {
        $client = app(SberReadOnlyApiClient::class);

        try {
            $client->assertAllowed('POST', '/fintech/api/v1/payments');
            $this->fail('The payment endpoint must be rejected.');
        } catch (ReadOnlyViolationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ReadOnlyViolationException::class);

        $client->request(BankEnvironment::Sandbox, 'payments.create');
    }

    public function test_non_allowlisted_http_method_is_rejected(): void
    {
        $this->expectException(ReadOnlyViolationException::class);

        app(SberReadOnlyApiClient::class)
            ->assertAllowed('POST', '/fintech/api/v2/statement/transactions');
    }

    public function test_sandbox_environment_cannot_be_redirected_to_a_production_host(): void
    {
        config([
            'banking.sber.environments.sandbox.api_base_url' => 'https://prod.example.test',
            'banking.sber.allowed_hosts' => [
                'sber-auth.example.test',
                'sber-api.example.test',
                'prod.example.test',
            ],
        ]);

        try {
            app(SberReadOnlyApiClient::class)->validatedBaseUrl(
                BankEnvironment::Sandbox,
                'api'
            );
            $this->fail('A production host must not pass the sandbox host allowlist.');
        } catch (BankConfigurationException) {
            Http::assertNothingSent();
            $this->assertTrue(true);
        }
    }

    public function test_base_url_cannot_add_a_path_prefix_to_allowlisted_endpoints(): void
    {
        config([
            'banking.sber.environments.sandbox.api_base_url' => 'https://sber-api.example.test/unexpected-prefix',
        ]);

        $this->expectException(BankConfigurationException::class);

        app(SberReadOnlyApiClient::class)->validatedBaseUrl(
            BankEnvironment::Sandbox,
            'api'
        );
    }

    public function test_token_exchange_uses_only_allowlisted_oauth_endpoint_and_keeps_numbers_as_strings(): void
    {
        Http::fake([
            'https://sber-auth.example.test/ic/sso/api/v2/oauth/token' => Http::response(
                '{"access_token":"safe","refresh_token":"safe-refresh","expires_in":3600.0}',
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $payload = app(SberReadOnlyApiClient::class)->request(
            BankEnvironment::Sandbox,
            'oauth.token',
            form: ['grant_type' => 'authorization_code', 'code' => 'one-time-code'],
        );

        $this->assertSame('3600.0', $payload['expires_in']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://sber-auth.example.test/ic/sso/api/v2/oauth/token'
            && $request['grant_type'] === 'authorization_code'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_daily_statement_uses_mtls_options_bearer_token_and_get_query(): void
    {
        $this->bindTokenManager('access-token');
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/transactions*' => Http::response([
                'transactions' => [],
            ]),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);

        $payload = app(SberReadOnlyApiClient::class)->request(
            BankEnvironment::Sandbox,
            'statement.daily',
            query: [
                'accountNumber' => '40702810000000000001',
                'statementDate' => '2026-07-24',
                'page' => 1,
            ],
            connection: $connection,
        );

        $this->assertSame([], $payload['transactions']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->data()['accountNumber'] === '40702810000000000001');
    }

    public function test_401_refreshes_once_and_repeats_original_request_once(): void
    {
        $tokens = Mockery::mock(SberTokenManager::class);
        $tokens->shouldReceive('accessTokenFor')->once()->andReturn('old-token');
        $tokens->shouldReceive('refreshAfterUnauthorized')
            ->once()
            ->with(Mockery::type(BankConnection::class), 'old-token')
            ->andReturn('new-token');
        $this->app->instance(SberTokenManager::class, $tokens);
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/transactions*' => Http::sequence()
                ->push(['cause' => 'UNAUTHORIZED'], 401)
                ->push(['transactions' => []], 200),
        ]);

        app(SberReadOnlyApiClient::class)->request(
            BankEnvironment::Sandbox,
            'statement.daily',
            query: [
                'accountNumber' => '40702810000000000001',
                'statementDate' => '2026-07-24',
                'page' => 1,
            ],
            connection: new BankConnection(['environment' => 'sandbox']),
        );

        Http::assertSentCount(2);
    }

    public function test_403_is_a_non_retryable_scope_error(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::response(['cause' => 'ACTION_ACCESS_EXCEPTION'], 403)]);

        $this->expectException(BankAuthorizationException::class);

        $this->dailyRequest();
    }

    public function test_bank_error_message_is_redacted_before_it_leaves_the_client(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::response([
            'cause' => 'VALIDATION_ERROR',
            'message' => 'Account 40702810000000000001 access_token=top-secret is invalid.',
        ], 400)]);

        try {
            $this->dailyRequest();
            $this->fail('Expected validation exception.');
        } catch (BankValidationException $exception) {
            $this->assertStringNotContainsString('40702810000000000001', $exception->getMessage());
            $this->assertStringNotContainsString('top-secret', $exception->getMessage());
            $this->assertSame('VALIDATION_ERROR', $exception->bankCause);
        }
    }

    public function test_429_exposes_retry_after_without_unbounded_internal_retry(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::response(
            ['cause' => 'TOO_MANY_REQUESTS'],
            429,
            ['Retry-After' => '17'],
        )]);

        try {
            $this->dailyRequest();
            $this->fail('Expected rate-limit exception.');
        } catch (BankRateLimitException $exception) {
            $this->assertSame(17, $exception->retryAfterSeconds);
            Http::assertSentCount(1);
        }
    }

    public function test_429_accepts_an_http_date_retry_after_value(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::response(
            ['cause' => 'TOO_MANY_REQUESTS'],
            429,
            ['Retry-After' => gmdate(DATE_RFC7231, time() + 120)],
        )]);

        try {
            $this->dailyRequest();
            $this->fail('Expected rate-limit exception.');
        } catch (BankRateLimitException $exception) {
            $this->assertGreaterThanOrEqual(115, $exception->retryAfterSeconds);
            $this->assertLessThanOrEqual(120, $exception->retryAfterSeconds);
            Http::assertSentCount(1);
        }
    }

    public function test_202_is_treated_as_retryable_bank_unavailability(): void
    {
        Http::fake(['*' => Http::response([], 202)]);
        $this->bindTokenManager();

        try {
            $this->dailyRequest();
            $this->fail('Expected unavailable exception for HTTP 202.');
        } catch (BankUnavailableException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(202, $exception->httpStatus);
        }
    }

    public function test_503_is_treated_as_retryable_bank_unavailability(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->bindTokenManager();

        try {
            $this->dailyRequest();
            $this->fail('Expected unavailable exception for HTTP 503.');
        } catch (BankUnavailableException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(503, $exception->httpStatus);
        }
    }

    public function test_transport_failure_is_normalized_without_leaking_request_data(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::failedConnection('Connection timed out')]);

        try {
            $this->dailyRequest();
            $this->fail('A transport failure must be normalized.');
        } catch (BankNetworkTimeoutException $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertSame('statement.daily', $exception->endpointAlias);
        }
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->bindTokenManager();
        Http::fake(['*' => Http::response('{"transactions":', 200)]);

        $this->expectException(BankMalformedResponseException::class);

        $this->dailyRequest();
    }

    public function test_pagination_rejects_foreign_host_and_unexpected_query(): void
    {
        $client = app(SberReadOnlyApiClient::class);

        foreach ([
            'https://foreign.example/fintech/api/v2/statement/transactions?page=2',
            'https://sber-api.example.test/fintech/api/v2/statement/transactions?page=2&url=https%3A%2F%2Fforeign.example',
        ] as $url) {
            try {
                $client->validatePaginationUrl(BankEnvironment::Sandbox, 'statement.daily', $url);
                $this->fail('Unsafe pagination URL must be rejected.');
            } catch (ReadOnlyViolationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_daily_statement_follows_allowlisted_pagination_and_stops_without_next_link(): void
    {
        $this->bindTokenManager(count: 2);
        $next = '?accountNumber=40702810000000000001&statementDate=2026-07-24&page=2';
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/transactions*' => Http::sequence()
                ->push([
                    'transactions' => [[
                        'operationId' => 'operation-1',
                        'operationDate' => '2026-07-24',
                        'direction' => 'CREDIT',
                        'amount' => '10.00',
                    ]],
                    'links' => [['rel' => 'next', 'href' => $next]],
                ])
                ->push([
                    'transactions' => [[
                        'operationId' => 'operation-2',
                        'operationDate' => '2026-07-24',
                        'direction' => 'DEBIT',
                        'amount' => '3.00',
                    ]],
                    'links' => [],
                ]),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);
        $account = new BankAccount(['account_number' => '40702810000000000001']);

        $statement = app(SberStatementService::class)->daily(
            $connection,
            $account,
            CarbonImmutable::parse('2026-07-24'),
        );

        $this->assertSame(2, $statement->pages);
        $this->assertSame(
            ['operation-1', 'operation-2'],
            $statement->transactions->pluck('operationId')->all()
        );
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ($request->data()['page'] ?? null) === '2');
    }

    public function test_incremental_statement_uses_last_modify_window(): void
    {
        $this->bindTokenManager();
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/increment*' => Http::response([
                'transactions' => [],
                'reloadTime' => '2026-07-24T14:00:00.000+03:00',
            ]),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);
        $account = new BankAccount(['account_number' => '40702810000000000001']);

        $statement = app(SberStatementService::class)->incremental(
            $connection,
            $account,
            CarbonImmutable::parse('2026-07-24 12:00:00', 'Europe/Moscow'),
            CarbonImmutable::parse('2026-07-24 13:00:00', 'Europe/Moscow'),
        );

        $this->assertSame(1, $statement->pages);
        $this->assertNotNull($statement->reloadTime);
        Http::assertSent(fn (Request $request): bool => $request->data()['lastModifyDate'] === '2026-07-24T12:00:00.000'
            && $request->data()['lastModifyDateTo'] === '2026-07-24T13:00:00.000');
    }

    public function test_daily_balance_uses_read_only_summary_endpoint(): void
    {
        $this->bindTokenManager();
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/summary*' => Http::response([
                'closingBalance' => [
                    'amount' => '1000004747.11',
                    'currencyName' => 'RUR',
                ],
                'composedDateTime' => '2026-07-24T12:40:53+03:00',
            ]),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);
        $account = new BankAccount(['account_number' => '40702810000000000001']);

        $balance = app(SberStatementService::class)->summary(
            $connection,
            $account,
            CarbonImmutable::parse('2026-07-24'),
        );

        $this->assertSame('1000004747.11', $balance->amount);
        $this->assertSame('RUB', $balance->currency);
        $this->assertSame('2026-07-24', $balance->statementDate->format('Y-m-d'));
        $this->assertSame('2026-07-24 12:40:53', $balance->asOf->format('Y-m-d H:i:s'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/fintech/api/v2/statement/summary'
            && $request->data()['accountNumber'] === '40702810000000000001'
            && $request->data()['statementDate'] === '2026-07-24');
    }

    public function test_data_not_found_is_contextually_empty_for_a_statement_and_balance(): void
    {
        $this->bindTokenManager(count: 2);
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/transactions*' => Http::response([
                'cause' => 'DATA_NOT_FOUND',
            ], 404),
            'https://sber-api.example.test/fintech/api/v2/statement/summary*' => Http::response([
                'cause' => 'DATA_NOT_FOUND_EXCEPTION',
            ], 404),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);
        $account = new BankAccount(['account_number' => '40702810000000000001']);
        $date = CarbonImmutable::parse('2026-07-24');

        $statement = app(SberStatementService::class)->daily($connection, $account, $date);
        $balance = app(SberStatementService::class)->summary($connection, $account, $date);

        $this->assertCount(0, $statement->transactions);
        $this->assertNull($balance);
        Http::assertSentCount(2);
    }

    public function test_single_operation_uses_documented_literal_endpoint_and_query_parameters(): void
    {
        $this->bindTokenManager();
        Http::fake([
            'https://sber-api.example.test/fintech/api/v2/statement/transactionId*' => Http::response([
                'operation' => [
                    'operationId' => 'operation-7',
                    'operationDate' => '2026-07-24',
                    'amount' => '70.00',
                ],
            ]),
        ]);
        $connection = new BankConnection(['environment' => 'sandbox']);
        $account = new BankAccount(['account_number' => '40702810000000000001']);

        $operation = app(SberStatementService::class)->transaction(
            $connection,
            $account,
            'operation-7',
            CarbonImmutable::parse('2026-07-24'),
        );

        $this->assertSame('operation-7', $operation->operationId);
        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH)
                === '/fintech/api/v2/statement/transactionId'
            && $request->data()['operationId'] === 'operation-7'
            && $request->data()['statementDate'] === '2026-07-24');
    }

    public function test_user_info_accounts_are_normalized_and_masked(): void
    {
        $this->bindTokenManager();
        Http::fake([
            'https://sber-auth.example.test/ic/sso/api/v2/oauth/user-info' => Http::response([
                'accounts' => [[
                    'id' => 'account-id',
                    'accountNumber' => '40702810000000000001',
                    'currency' => 'RUB',
                    'status' => 'ACTIVE',
                    'balance' => ['amount' => '123.45', 'asOf' => '2026-07-24T12:00:00+03:00'],
                ]],
            ]),
        ]);
        $payload = app(SberReadOnlyApiClient::class)->request(
            BankEnvironment::Sandbox,
            'oauth.user_info',
            connection: new BankConnection(['environment' => 'sandbox']),
        );
        $accounts = app(SberResponseNormalizer::class)->accounts($payload);

        $this->assertCount(1, $accounts);
        $this->assertSame('40702810000000000001', $accounts->first()->accountNumber);
        $this->assertNotSame($accounts->first()->accountNumber, $accounts->first()->maskedNumber);
        $this->assertSame('123.45', $accounts->first()->balance->amount);
    }

    private function bindTokenManager(string $token = 'token', int $count = 1): void
    {
        $manager = Mockery::mock(SberTokenManager::class);
        $manager->shouldReceive('accessTokenFor')->times($count)->andReturn($token);
        $this->app->instance(SberTokenManager::class, $manager);
    }

    private function dailyRequest(): array|string
    {
        return app(SberReadOnlyApiClient::class)->request(
            BankEnvironment::Sandbox,
            'statement.daily',
            query: [
                'accountNumber' => '40702810000000000001',
                'statementDate' => '2026-07-24',
                'page' => 1,
            ],
            connection: new BankConnection(['environment' => 'sandbox']),
        );
    }
}
