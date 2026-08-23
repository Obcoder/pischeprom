<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Infrastructure\AiSales\Probes\RealGitRepositoryStateInspector;
use App\Models\Entity;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchResult;
use App\Models\Unit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;
use Tests\TestCase;

class ExistingYandexLiveProbeCommandTest extends TestCase
{
    private ?string $databasePath = null;

    /** @var array<string, mixed> */
    private array $originalDatabaseConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->originalDatabaseConfig = [
            'default' => config('database.default'),
            'sqlite_database' => config('database.connections.sqlite.database'),
        ];
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config()->set([
            'database.default' => $this->originalDatabaseConfig['default'],
            'database.connections.sqlite.database' => $this->originalDatabaseConfig['sqlite_database'],
        ]);

        if ($this->databasePath !== null && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_live_probe_uses_one_existing_yandex_request_and_bounded_public_fetch(): void
    {
        $this->migrateIsolatedDatabase();
        $repositoryState = $this->bindRepositoryState();
        $apiKey = 'stage09b-'.Str::random(48);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => 'stage09b-http-fake-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
            'ai-sales.prospecting.live_probe_enabled' => true,
        ]);
        app()->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'company.synthetic.example' => ['93.184.216.34'],
        ]));

        $xml = <<<'XML'
<yandexsearch><response><results><grouping><group><doc>
<url>https://company.synthetic.example/about?utm_source=stage09b</url>
<domain>company.synthetic.example</domain>
<title>Синтетический покупатель брокколи</title>
<passages><passage>Публичный сайт синтетической организации.</passage></passages>
</doc></group></grouping></results></response></yandexsearch>
XML;

        Http::fake(function (Request $request) use ($xml) {
            return match ($request->url()) {
                'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response(
                    ['rawData' => base64_encode($xml)],
                    200,
                    ['Content-Type' => 'application/json', 'X-Request-Id' => 'stage09b-safe-request'],
                ),
                'https://company.synthetic.example/robots.txt' => Http::response(
                    "User-agent: *\nDisallow:\n",
                    200,
                    ['Content-Type' => 'text/plain'],
                ),
                'https://company.synthetic.example/about' => Http::response(
                    '<!doctype html><html><head><title>Синтетическая компания</title></head>'
                    .'<body><h1>Покупатель брокколи</h1><p>Оптовое пищевое производство.</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html'],
                ),
                default => Http::response(['error' => 'unexpected test URL'], 500),
            };
        });

        $exitCode = Artisan::call('ai-sales:probe-existing-yandex-discovery', [
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('buyer_broccoli_spb_v1', $output);
        $this->assertStringContainsString('"yandex_requests": 1', $output);
        $this->assertStringContainsString('"total_live_http": 3', $output);
        $this->assertStringContainsString('"candidate_count": 1', $output);
        $this->assertStringContainsString('"unit_changes": 0', $output);
        $this->assertStringContainsString('"entity_changes": 0', $output);
        $this->assertStringContainsString('"worktree_state": "clean_committed_stage09b"', $output);
        $this->assertStringNotContainsString($apiKey, $output);
        $this->assertStringNotContainsString('<html', mb_strtolower($output));
        $this->assertSame(1, $repositoryState->inspectCalls);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://searchapi.api.cloud.yandex.net/v2/web/search'
            && $request->hasHeader('Authorization', 'Api-Key '.$apiKey));
        $this->assertDatabaseCount('prospecting_search_results', 1);
        $this->assertDatabaseCount('prospecting_public_fetches', 1);
        $this->assertSame(1, ProspectingCandidate::query()->count());
        $this->assertSame(0, Unit::query()->count());
        $this->assertSame(0, Entity::query()->count());
        $result = ProspectingSearchResult::query()->firstOrFail();
        $this->assertSame('https://company.synthetic.example/about', $result->canonical_url);
        $this->assertFalse((bool) config('ai-sales.prospecting.query_planning_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.search_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.page_fetch_enabled'));
    }

    public function test_live_probe_requires_explicit_gate_confirmation_and_file_backed_sqlite(): void
    {
        Http::fake();
        $apiKey = 'stage09b-'.Str::random(48);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => 'stage09b-http-fake-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
            'ai-sales.prospecting.live_probe_enabled' => false,
        ]);

        $this->artisan('ai-sales:probe-existing-yandex-discovery', [
            '--live' => true,
            '--yes' => true,
        ])->assertFailed()->expectsOutputToContain('stage09b_live_probe_disabled');
        Http::assertNothingSent();

        config()->set('ai-sales.prospecting.live_probe_enabled', true);
        config()->set('database.connections.sqlite.database', ':memory:');
        $this->artisan('ai-sales:probe-existing-yandex-discovery', [
            '--live' => true,
            '--yes' => true,
        ])->assertFailed()->expectsOutputToContain('stage09b_file_sqlite_required');
        Http::assertNothingSent();
    }

    public function test_yandex_auth_failure_stops_before_any_public_fetch(): void
    {
        $this->migrateIsolatedDatabase();
        $this->bindRepositoryState();
        $apiKey = 'stage09b-'.Str::random(48);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => 'stage09b-http-fake-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
            'ai-sales.prospecting.live_probe_enabled' => true,
        ]);
        Http::fake([
            'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response(
                ['error' => ['message' => 'redacted fake auth failure']],
                401,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $exitCode = Artisan::call('ai-sales:probe-existing-yandex-discovery', [
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('yandex_search_http_401', $output);
        $this->assertStringContainsString('"yandex_requests": 1', $output);
        $this->assertStringContainsString('"public_requests": 0', $output);
        $this->assertStringNotContainsString($apiKey, $output);
        $this->assertStringNotContainsString('redacted fake auth failure', $output);
        Http::assertSentCount(1);
    }

    public function test_testing_environment_uses_real_inspector_without_a_test_binding_or_runtime_bypass(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertInstanceOf(
            RealGitRepositoryStateInspector::class,
            app(GitRepositoryStateInspectorInterface::class),
        );
        $this->assertStringStartsWith('Tests\\', FakeGitRepositoryStateInspector::class);

        foreach ([
            app_path('Domain/AiSales/Probes/ExistingYandexSecretExposureScanner.php'),
            app_path('Infrastructure/AiSales/Probes/RealGitRepositoryStateInspector.php'),
        ] as $runtimeFile) {
            $contents = file_get_contents($runtimeFile);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('runningUnitTests', $contents);
            $this->assertStringNotContainsString('APP_ENV', $contents);
            $this->assertStringNotContainsString('PHPUnit', $contents);
            $this->assertStringNotContainsString('class_exists', $contents);
            $this->assertStringNotContainsString('FakeGitRepositoryStateInspector', $contents);
        }
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRepositoryStates')]
    public function test_invalid_repository_state_blocks_live_before_http(
        array $overrides,
        string $safeCode,
    ): void {
        $this->configureEmptyIsolatedDatabase();
        $repositoryState = $this->bindRepositoryState($this->repositoryState($overrides));
        $apiKey = 'stage09b-'.Str::random(48);
        $folderId = 'stage09b-folder-'.Str::random(32);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => $folderId,
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
            'ai-sales.prospecting.live_probe_enabled' => true,
        ]);
        Http::fake();

        $exitCode = Artisan::call('ai-sales:probe-existing-yandex-discovery', [
            '--live' => true,
            '--yes' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString($safeCode, $output);
        $this->assertStringNotContainsString($apiKey, $output);
        $this->assertStringNotContainsString($folderId, $output);
        $this->assertSame(1, $repositoryState->inspectCalls);
        Http::assertNothingSent();
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidRepositoryStates(): iterable
    {
        $firstHash = str_repeat('a', 40);
        $secondHash = str_repeat('b', 40);
        $subject = 'test(ai-sales): add bounded existing Yandex live acceptance probe';

        yield 'dirty modified tree' => [
            ['modifiedChanges' => 1],
            'stage09b_modified_changes_blocked',
        ];
        yield 'staged file' => [
            ['stagedChanges' => 1],
            'stage09b_staged_changes_blocked',
        ];
        yield 'untracked file' => [
            ['untrackedChanges' => 1],
            'stage09b_untracked_changes_blocked',
        ];
        yield 'wrong branch' => [
            ['branch' => 'feature/not-ai-sales-agents'],
            'stage09b_branch_mismatch',
        ];
        yield 'stage09 is not ancestor' => [
            ['baseIsAncestor' => false, 'commitsAfterBase' => []],
            'stage09b_stage09_not_ancestor',
        ];
        yield 'extra commit after stage09' => [
            [
                'head' => $secondHash,
                'commitsAfterBase' => [
                    ['hash' => $firstHash, 'subject' => $subject],
                    ['hash' => $secondHash, 'subject' => 'unexpected extra commit'],
                ],
            ],
            'stage09b_commit_count_invalid',
        ];
        yield 'wrong stage09b subject' => [
            ['commitsAfterBase' => [['hash' => $firstHash, 'subject' => 'wrong subject']]],
            'stage09b_commit_subject_invalid',
        ];
        yield 'head is not stage09b commit' => [
            ['head' => $secondHash],
            'stage09b_head_not_stage09b_commit',
        ];
    }

    private function migrateIsolatedDatabase(): void
    {
        $this->configureEmptyIsolatedDatabase();

        $status = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $status, 'Full migrations must apply to isolated Stage 09B SQLite.');
    }

    private function configureEmptyIsolatedDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-stage09b-test-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        chmod($path, 0600);
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
    }

    private function bindRepositoryState(?GitRepositoryState $state = null): FakeGitRepositoryStateInspector
    {
        $fake = new FakeGitRepositoryStateInspector($state ?? $this->repositoryState());
        app()->instance(GitRepositoryStateInspectorInterface::class, $fake);

        return $fake;
    }

    /** @param array<string, mixed> $overrides */
    private function repositoryState(array $overrides = []): GitRepositoryState
    {
        $hash = str_repeat('a', 40);
        $values = array_replace([
            'branch' => 'feature/ai-sales-agents',
            'head' => $hash,
            'baseIsAncestor' => true,
            'commitsAfterBase' => [[
                'hash' => $hash,
                'subject' => 'test(ai-sales): add bounded existing Yandex live acceptance probe',
            ]],
            'stagedChanges' => 0,
            'modifiedChanges' => 0,
            'untrackedChanges' => 0,
        ], $overrides);

        return new GitRepositoryState(
            branch: $values['branch'],
            head: $values['head'],
            baseIsAncestor: $values['baseIsAncestor'],
            commitsAfterBase: $values['commitsAfterBase'],
            stagedChanges: $values['stagedChanges'],
            modifiedChanges: $values['modifiedChanges'],
            untrackedChanges: $values['untrackedChanges'],
        );
    }
}
