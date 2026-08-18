<?php

namespace Tests\Feature\Mail;

use App\Http\Middleware\ThrottleVerifiedUnisenderWebhookRequest;
use App\Http\Middleware\VerifyUnisenderWebhookRequest;
use App\Jobs\ProcessUnisenderWebhookJob;
use App\Models\MailingEvent;
use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\UnisenderWebhookIngress;
use App\Services\CommercialOffers\VerifiedUnisenderWebhookRateLimiter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UnisenderWebhookStatelessIngressTest extends TestCase
{
    private const API_KEY = 'stateless-ingress-test-api-key';

    private const RAW_CANARY = 'stateless-raw-body-canary';

    private const RECIPIENT = 'stateless-recipient@example.test';

    /** @var list<string> */
    private array $writeQueries = [];

    /** @var array<string, array{env_exists: bool, env: mixed, server_exists: bool, server: mixed, getenv: string|false}> */
    private array $originalEnvironment = [];

    private string $databasePath;

    protected function setUp(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'pischeprom_unisender_v2_');
        if ($databasePath === false) {
            throw new RuntimeException('Unable to create isolated SQLite database.');
        }
        $this->databasePath = $databasePath;

        $this->overrideEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=',
            'CACHE_STORE' => 'database',
            'CACHE_PREFIX' => 'unisender_v2_test_cache_',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->databasePath,
            'DB_CACHE_CONNECTION' => 'sqlite',
            'DB_QUEUE_CONNECTION' => 'sqlite',
            'QUEUE_CONNECTION' => 'database',
            'SESSION_CONNECTION' => 'sqlite',
            'SESSION_DRIVER' => 'database',
            'TELESCOPE_ENABLED' => 'false',
        ]);

        parent::setUp();

        config([
            'app.env' => 'testing',
            'app.debug' => false,
            'cache.default' => 'database',
            'cache.prefix' => 'unisender_v2_test_cache_',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'database.connections.sqlite.foreign_key_constraints' => true,
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'session.driver' => 'database',
            'session.connection' => 'sqlite',
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_key' => self::API_KEY,
            'services.unisender_go.webhook_max_parallel' => 10,
            'services.unisender_go.webhook_queue_connection' => 'database',
            'services.unisender_go.webhook_queue' => 'mailing-webhooks',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::connection('sqlite')->getPdo();

        (include base_path('database/migrations/0001_01_01_000000_create_users_table.php'))->up();
        (include base_path('database/migrations/0001_01_01_000001_create_cache_table.php'))->up();
        (include base_path('database/migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        (include base_path('database/migrations/2026_06_21_130000_create_commercial_offer_mailings_tables.php'))->up();
        DB::statement('create table sendings (id integer primary key autoincrement)');
        DB::statement('create table mail_messages (id integer primary key autoincrement)');
        (include base_path('database/migrations/2026_08_17_123000_harden_unisender_provider_persistence.php'))->up();

        Http::preventStrayRequests();
        Mail::fake();

        DB::listen(function (QueryExecuted $query): void {
            $summary = $this->writeSummary($query->sql);
            if ($summary !== null) {
                $this->writeQueries[] = $summary;
            }
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            DB::disconnect('sqlite');
        }

        parent::tearDown();
        $this->restoreEnvironment();

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRequestCases(): array
    {
        return [
            'wrong method' => ['wrong_method'],
            'missing content type' => ['missing_content_type'],
            'wrong content type' => ['wrong_content_type'],
            'unsupported encoding' => ['unsupported_encoding'],
            'oversized body' => ['oversized_body'],
            'malformed json' => ['malformed_json'],
            'missing auth' => ['missing_auth'],
            'invalid auth' => ['invalid_auth'],
            'invalid schema' => ['invalid_schema'],
            'event count over maximum' => ['over_event_cap'],
        ];
    }

    #[DataProvider('invalidRequestCases')]
    public function test_unverified_requests_make_zero_database_writes_under_production_like_drivers(string $case): void
    {
        $this->assertProductionLikeIsolation();
        $before = $this->tableCounts();
        $this->writeQueries = [];
        Log::spy();

        [$method, $body, $server, $status] = $this->invalidRequest($case);
        $response = $this->call($method, '/webhooks/unisender-go', [], [], [], $server, $body);

        $response->assertStatus($status)
            ->assertHeaderMissing('Set-Cookie')
            ->assertDontSee(self::RAW_CANARY)
            ->assertDontSee(self::RECIPIENT)
            ->assertDontSee(self::API_KEY);

        $this->assertSame([], $this->writeQueries, $case.' produced database mutations.');
        $this->assertSame($before, $this->tableCounts(), $case.' changed a table row count.');
        $this->assertDatabaseCount('cache', 0);
        $this->assertDatabaseCount('cache_locks', 0);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('mailing_webhook_calls', 0);
        $this->assertDatabaseCount('mailing_events', 0);
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_valid_and_duplicate_requests_use_only_safe_post_verification_state(): void
    {
        $this->assertProductionLikeIsolation();
        $body = $this->signedPayload([
            'events_by_user' => [['events' => [$this->event('stateless-valid-event')]]],
            'request_marker' => self::RAW_CANARY,
        ]);

        $this->writeQueries = [];
        $this->rawPost($body)->assertOk()->assertJson([
            'status' => 'ok',
            'duplicate' => false,
            'accepted_events' => 1,
        ])->assertHeaderMissing('Set-Cookie');

        $this->assertContains('insert:cache', $this->writeQueries);
        $this->assertContains('insert:mailing_webhook_calls', $this->writeQueries);
        $this->assertContains('insert:mailing_events', $this->writeQueries);
        $this->assertContains('insert:jobs', $this->writeQueries);
        $this->assertSame(
            ['cache', 'jobs', 'mailing_events', 'mailing_webhook_calls'],
            $this->writtenTables(),
        );
        $this->assertDatabaseCount('cache', 2);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('mailing_webhook_calls', 1);
        $this->assertDatabaseCount('mailing_events', 1);
        $this->assertDatabaseCount('jobs', 1);

        $call = MailingWebhookCall::query()->sole();
        $event = MailingEvent::query()->sole();
        $this->assertNull($call->raw_payload);
        $this->assertNull($call->parsed_payload);
        $this->assertNull($call->error_message);
        $this->assertNull($event->payload);
        $this->assertNull($event->email);
        $this->assertNull($event->url);
        $this->assertNull($event->ip);
        $this->assertNull($event->user_agent);

        $queued = DB::table('jobs')->sole();
        $this->assertSame('mailing-webhooks', $queued->queue);
        $payload = json_decode($queued->payload, true, 32, JSON_THROW_ON_ERROR);
        $job = unserialize((string) ($payload['data']['command'] ?? ''), ['allowed_classes' => [ProcessUnisenderWebhookJob::class]]);
        $this->assertInstanceOf(ProcessUnisenderWebhookJob::class, $job);
        $this->assertSame([$event->id], $job->eventIds);
        $this->assertSame(1, $job->tries);
        $this->assertSafeStorage($body);

        $this->writeQueries = [];
        $this->rawPost($body)->assertOk()->assertJsonPath('duplicate', true)->assertHeaderMissing('Set-Cookie');
        $this->assertDatabaseCount('mailing_webhook_calls', 1);
        $this->assertDatabaseCount('mailing_events', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertSame(['cache'], $this->writtenTables());
        $this->assertSafeStorage($body);
    }

    public function test_verified_throttle_blocks_persistence_and_uses_only_safe_cache_state(): void
    {
        $limiter = app(VerifiedUnisenderWebhookRateLimiter::class);
        for ($attempt = 0; $attempt < $limiter->maxAttempts(); $attempt++) {
            RateLimiter::hit(VerifiedUnisenderWebhookRateLimiter::CACHE_KEY, 60);
        }

        $body = $this->signedPayload([
            'events_by_user' => [['events' => [$this->event('stateless-throttled-event')]]],
            'request_marker' => self::RAW_CANARY,
        ]);
        $cacheBefore = DB::table('cache')->get()->map(fn ($row): array => (array) $row)->all();
        $this->writeQueries = [];

        $this->rawPost($body)
            ->assertStatus(429)
            ->assertJsonPath('code', MailProviderSafeErrorCode::RateLimited->value)
            ->assertHeader('Retry-After')
            ->assertHeaderMissing('Set-Cookie');

        $this->assertDatabaseCount('mailing_webhook_calls', 0);
        $this->assertDatabaseCount('mailing_events', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertSame([], $this->writeQueries);
        $this->assertSame($cacheBefore, DB::table('cache')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSafeStorage($body);
    }

    public function test_verified_limiter_backend_failure_is_safe_and_blocks_domain_persistence(): void
    {
        $body = $this->signedPayload([
            'events_by_user' => [['events' => [$this->event('stateless-cache-failure-event')]]],
            'request_marker' => self::RAW_CANARY,
        ]);
        DB::statement('drop table cache');
        $this->writeQueries = [];
        Log::spy();

        $this->rawPost($body)
            ->assertStatus(503)
            ->assertJsonPath('code', MailProviderSafeErrorCode::ProcessingFailedSafe->value)
            ->assertHeaderMissing('Set-Cookie')
            ->assertDontSee(self::RAW_CANARY)
            ->assertDontSee(self::RECIPIENT)
            ->assertDontSee(self::API_KEY);

        $this->assertSame([], $this->writeQueries);
        $this->assertDatabaseCount('mailing_webhook_calls', 0);
        $this->assertDatabaseCount('mailing_events', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('sessions', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return $message === 'Verified Unisender webhook limiter unavailable'
                && ($context['provider'] ?? null) === 'unisender_go'
                && ($context['safe_error_code'] ?? null) === MailProviderSafeErrorCode::ProcessingFailedSafe->value
                && ! str_contains($serialized, self::RAW_CANARY)
                && ! str_contains($serialized, self::RECIPIENT)
                && ! str_contains($serialized, self::API_KEY);
        });
    }

    public function test_route_registry_is_dedicated_stateless_and_has_no_runtime_test_bypass(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.unisender-go.handle');
        $this->assertNotNull($route);
        $this->assertSame('/webhooks/unisender-go', '/'.$route->uri());
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame([
            VerifyUnisenderWebhookRequest::class,
            ThrottleVerifiedUnisenderWebhookRequest::class,
        ], array_values($route->gatherMiddleware()));

        $forbiddenMiddleware = [
            'web',
            'throttle:unisender-webhook',
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ];
        foreach ($forbiddenMiddleware as $middleware) {
            $this->assertNotContains($middleware, $route->gatherMiddleware());
        }

        $global = app(Kernel::class)->getGlobalMiddleware();
        foreach ($forbiddenMiddleware as $middleware) {
            $this->assertNotContains($middleware, $global);
        }

        $source = implode("\n", array_map(
            fn (string $path): string => (string) file_get_contents(base_path($path)),
            [
                'routes/provider-webhooks.php',
                'app/Http/Middleware/VerifyUnisenderWebhookRequest.php',
                'app/Http/Middleware/ThrottleVerifiedUnisenderWebhookRequest.php',
                'app/Services/CommercialOffers/VerifiedUnisenderWebhookRateLimiter.php',
            ],
        ));
        $this->assertStringNotContainsString('runningUnitTests', $source);
        $this->assertStringNotContainsString('APP_ENV', $source);
        $this->assertStringNotContainsString('class_exists', $source);
        $this->assertStringNotContainsString('throttle:unisender-webhook', $source);
    }

    private function assertProductionLikeIsolation(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame($this->databasePath, config('database.connections.sqlite.database'));
        $this->assertNotSame(':memory:', $this->databasePath);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($this->databasePath)));
        $this->assertSame('database', config('cache.default'));
        $this->assertSame('database', config('session.driver'));
        $this->assertSame('database', config('queue.default'));
        $this->assertFileExists($this->databasePath);
    }

    /**
     * @return array{string, string, array<string, string>, int}
     */
    private function invalidRequest(string $case): array
    {
        $validBody = $this->signedPayload([
            'events_by_user' => [['events' => [$this->event('invalid-case-event')]]],
            'request_marker' => self::RAW_CANARY,
        ]);
        $jsonServer = ['CONTENT_TYPE' => 'application/json'];

        return match ($case) {
            'wrong_method' => ['PUT', $validBody, $jsonServer, 405],
            'missing_content_type' => ['POST', $validBody, [], 415],
            'wrong_content_type' => ['POST', $validBody, ['CONTENT_TYPE' => 'text/plain'], 415],
            'unsupported_encoding' => ['POST', $validBody, $jsonServer + ['HTTP_CONTENT_ENCODING' => 'gzip'], 415],
            'oversized_body' => [
                'POST',
                str_repeat('x', UnisenderWebhookIngress::MAX_ENCODED_BODY_BYTES + 1),
                $jsonServer,
                413,
            ],
            'malformed_json' => ['POST', '{"raw":"'.self::RAW_CANARY.'"', $jsonServer, 400],
            'missing_auth' => [
                'POST',
                json_encode(['events_by_user' => [['events' => [$this->event('missing-auth')]]]], JSON_THROW_ON_ERROR),
                $jsonServer,
                403,
            ],
            'invalid_auth' => [
                'POST',
                json_encode([
                    'events_by_user' => [['events' => [$this->event('invalid-auth')]]],
                    'auth' => str_repeat('0', 32),
                    'raw' => self::RAW_CANARY,
                ], JSON_THROW_ON_ERROR),
                $jsonServer,
                403,
            ],
            'invalid_schema' => [
                'POST',
                $this->signedPayload(['events_by_user' => 'invalid', 'raw' => self::RAW_CANARY]),
                $jsonServer,
                422,
            ],
            'over_event_cap' => [
                'POST',
                $this->signedPayload([
                    'events_by_user' => [['events' => array_map(
                        fn (int $index): array => $this->event('over-cap-'.$index),
                        range(1, UnisenderWebhookIngress::MAX_EVENTS_PER_REQUEST + 1),
                    )]],
                    'raw' => self::RAW_CANARY,
                ]),
                $jsonServer,
                413,
            ],
            default => throw new RuntimeException('Unknown invalid request fixture.'),
        };
    }

    private function event(string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'event_name' => 'transactional_email_status',
            'event_data' => [
                'job_id' => 'stateless-job-id',
                'message_id' => 'stateless-message-id',
                'email' => self::RECIPIENT,
                'status' => 'delivered',
                'event_time' => '2026-08-19T00:00:00+00:00',
                'url' => 'https://example.test/'.self::RAW_CANARY,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPayload(array $payload): string
    {
        $payload['auth'] = 'signature-placeholder';
        $template = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $bodyForHash = str_replace('"signature-placeholder"', '"'.self::API_KEY.'"', $template);

        return str_replace('signature-placeholder', md5($bodyForHash), $template);
    }

    private function rawPost(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/webhooks/unisender-go', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'stateless-user-agent-canary',
            'REMOTE_ADDR' => '203.0.113.200',
        ], $body);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $tables = collect(DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
            ->pluck('name')
            ->sort()
            ->values();

        return $tables->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->count(),
        ])->all();
    }

    private function writeSummary(string $sql): ?string
    {
        if (preg_match('/\A\s*(insert(?:\s+or\s+\w+)?|replace|update|delete)\b/i', $sql, $operation) !== 1) {
            return null;
        }

        if (preg_match('/\b(?:into|update|from)\s+["`]?([a-z0-9_]+)/i', $sql, $table) !== 1) {
            return mb_strtolower($operation[1]).':unknown';
        }

        return str_starts_with(mb_strtolower($operation[1]), 'insert')
            ? 'insert:'.mb_strtolower($table[1])
            : mb_strtolower($operation[1]).':'.mb_strtolower($table[1]);
    }

    /** @return list<string> */
    private function writtenTables(): array
    {
        return collect($this->writeQueries)
            ->map(fn (string $summary): string => explode(':', $summary, 2)[1])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function assertSafeStorage(string $rawBody): void
    {
        $stored = json_encode([
            'cache' => DB::table('cache')->get(),
            'webhooks' => DB::table('mailing_webhook_calls')->get(),
            'events' => DB::table('mailing_events')->get(),
            'jobs' => DB::table('jobs')->get(),
            'sessions' => DB::table('sessions')->get(),
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($rawBody, $stored);
        $this->assertStringNotContainsString(self::RAW_CANARY, $stored);
        $this->assertStringNotContainsString(self::RECIPIENT, $stored);
        $this->assertStringNotContainsString(self::API_KEY, $stored);
        $this->assertStringNotContainsString('stateless-user-agent-canary', $stored);
        $this->assertStringNotContainsString('203.0.113.200', $stored);
    }

    /** @param array<string, string> $values */
    private function overrideEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->originalEnvironment[$key] = [
                'env_exists' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'server_exists' => array_key_exists($key, $_SERVER),
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    private function restoreEnvironment(): void
    {
        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['env_exists']) {
                $_ENV[$key] = $original['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($original['server_exists']) {
                $_SERVER[$key] = $original['server'];
            } else {
                unset($_SERVER[$key]);
            }

            if ($original['getenv'] === false) {
                putenv($key);
            } else {
                putenv($key.'='.$original['getenv']);
            }
        }
    }
}
