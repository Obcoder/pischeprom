<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryRepositoryGuard;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Domain\AiSales\FindBuyers\SubmitFindBuyersPlanForReview;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Models\Entity;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchJob;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;
use Tests\TestCase;

class FindBuyersCanaryCommandTest extends TestCase
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
        if ($this->databasePath !== null) {
            foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function test_dry_run_and_fake_live_canary_are_bounded_secret_safe_and_default_off_afterward(): void
    {
        $this->migrateIsolatedDatabase();
        $repositoryState = $this->bindRepositoryState();
        $apiKey = 'stage11b-'.Str::random(48);
        $folderId = 'stage11b-folder-'.Str::random(32);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => $folderId,
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
        $job = $this->createApprovedUiJob();
        $this->setDefaultOffFlags();

        $dryExit = Artisan::call('ai-sales:run-find-buyers-canary', [
            'job' => $job->public_id,
            '--dry-run' => true,
            '--retain-db' => true,
        ]);
        $dryOutput = Artisan::output();
        $this->assertSame(0, $dryExit, $dryOutput);
        $this->assertStringContainsString('dry_run_ready', $dryOutput);
        $this->assertStringContainsString('clean_committed_stage11b', $dryOutput);
        $this->assertStringContainsString('Брокколи', $dryOutput);
        $this->assertStringContainsString('Санкт-Петербург', $dryOutput);
        $this->assertStringContainsString('"browser_live_execute_allowed": false', $dryOutput);
        $this->assertStringContainsString('"total_live_http": 0', $dryOutput);
        $this->assertStringNotContainsString($apiKey, $dryOutput);
        $this->assertStringNotContainsString($folderId, $dryOutput);
        Http::assertNothingSent();
        $this->assertDatabaseCount('prospecting_search_executions', 0);

        app()->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'buyer.synthetic.example' => ['93.184.216.34'],
        ]));
        $xml = <<<'XML'
<yandexsearch><response><results><grouping><group><doc>
<url>https://buyer.synthetic.example/about?utm_source=stage11b</url>
<domain>buyer.synthetic.example</domain>
<title>Синтетический покупатель брокколи</title>
<passages><passage>Публичный сайт синтетической организации.</passage></passages>
</doc></group></grouping></results></response></yandexsearch>
XML;
        Http::fake(function (Request $request) use ($xml) {
            return match ($request->url()) {
                'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response(
                    ['rawData' => base64_encode($xml)],
                    200,
                    ['Content-Type' => 'application/json', 'X-Request-Id' => 'stage11b-safe-request'],
                ),
                'https://buyer.synthetic.example/robots.txt' => Http::response(
                    "User-agent: *\nDisallow:\n",
                    200,
                    ['Content-Type' => 'text/plain'],
                ),
                'https://buyer.synthetic.example/about' => Http::response(
                    '<html><head><title>Синтетическая компания</title></head>'
                    .'<body><h1>Покупатель брокколи</h1><p>Оптовое пищевое производство.</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html'],
                ),
                default => Http::response(['error' => 'unexpected test URL'], 500),
            };
        });

        $liveExit = Artisan::call('ai-sales:run-find-buyers-canary', [
            'job' => $job->public_id,
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);
        $liveOutput = Artisan::output();
        $this->assertSame(0, $liveExit, $liveOutput);
        $this->assertStringContainsString('"yandex_requests": 1', $liveOutput);
        $this->assertStringContainsString('"total_live_http": 3', $liveOutput);
        $this->assertStringContainsString('"normalized_results": 1', $liveOutput);
        $this->assertStringContainsString('"successful_pages": 1', $liveOutput);
        $this->assertStringContainsString('"candidate_count": 1', $liveOutput);
        $this->assertStringContainsString('"unit_changes": 0', $liveOutput);
        $this->assertStringContainsString('"entity_changes": 0', $liveOutput);
        $this->assertStringContainsString('"timeweb_requests": 0', $liveOutput);
        $this->assertStringContainsString('"email_sent": false', $liveOutput);
        $this->assertStringContainsString('"find_buyers_live_execution": false', $liveOutput);
        $this->assertStringNotContainsString($apiKey, $liveOutput);
        $this->assertStringNotContainsString($folderId, $liveOutput);
        $this->assertStringNotContainsString('<html', mb_strtolower($liveOutput));
        $this->assertStringNotContainsString('<yandexsearch', mb_strtolower($liveOutput));

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://searchapi.api.cloud.yandex.net/v2/web/search'
            && $request->hasHeader('Authorization', 'Api-Key '.$apiKey));
        $this->assertSame(2, $repositoryState->inspectCalls);
        $this->assertDatabaseCount('prospecting_search_executions', 1);
        $this->assertDatabaseCount('prospecting_search_results', 1);
        $this->assertDatabaseCount('prospecting_public_fetches', 1);
        $this->assertSame(1, ProspectingCandidate::query()->count());
        $this->assertSame(0, Unit::query()->count());
        $this->assertSame(0, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertFalse((bool) config('ai-sales.find_buyers.ui_enabled'));
        $this->assertFalse((bool) config('ai-sales.find_buyers.live_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.search_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.existing_yandex_provider_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.page_fetch_enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
    }

    public function test_fake_live_canary_accepts_robots_block_as_safe_partial_outcome(): void
    {
        $this->migrateIsolatedDatabase();
        $this->bindRepositoryState();
        $apiKey = 'stage11b-'.Str::random(48);
        config()->set([
            'services.yandex_search.api_key' => $apiKey,
            'services.yandex_search.folder_id' => 'stage11b-folder-'.Str::random(32),
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
        $job = $this->createApprovedUiJob();
        $this->setDefaultOffFlags();
        app()->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'blocked.synthetic.example' => ['93.184.216.34'],
        ]));
        $xml = <<<'XML'
<yandexsearch><response><results><grouping><group><doc>
<url>https://blocked.synthetic.example/private</url>
<domain>blocked.synthetic.example</domain>
<title>Синтетический закрытый robots результат</title>
<passages><passage>Публичная поисковая выдержка.</passage></passages>
</doc></group></grouping></results></response></yandexsearch>
XML;
        Http::fake(fn (Request $request) => match ($request->url()) {
            'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response(
                ['rawData' => base64_encode($xml)],
                200,
                ['Content-Type' => 'application/json'],
            ),
            'https://blocked.synthetic.example/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /\n",
                200,
                ['Content-Type' => 'text/plain'],
            ),
            default => Http::response(['error' => 'unexpected test URL'], 500),
        });

        $exitCode = Artisan::call('ai-sales:run-find-buyers-canary', [
            'job' => $job->public_id,
            '--live' => true,
            '--yes' => true,
            '--retain-db' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('robots_blocked', $output);
        $this->assertStringContainsString('"yandex_requests": 1', $output);
        $this->assertStringContainsString('"total_live_http": 2', $output);
        $this->assertStringContainsString('"successful_pages": 0', $output);
        $this->assertStringContainsString('"candidate_count": 0', $output);
        $this->assertStringContainsString('"unit_changes": 0', $output);
        $this->assertStringContainsString('"entity_changes": 0', $output);
        $this->assertStringNotContainsString($apiKey, $output);
        $this->assertStringNotContainsString('<yandexsearch', mb_strtolower($output));
        Http::assertSentCount(2);
        $this->assertSame(0, ProspectingCandidate::query()->count());
        $this->assertSame(0, Unit::query()->count());
        $this->assertSame(0, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertFalse((bool) config('ai-sales.find_buyers.live_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.search_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.page_fetch_enabled'));
    }

    private function migrateIsolatedDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-stage11b-test-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        chmod($path, 0600);
        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');

        $status = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->assertSame(0, $status, 'Full migrations must apply to isolated Stage 11B SQLite.');
    }

    private function createApprovedUiJob(): ProspectingSearchJob
    {
        $this->setUiPreparationFlags();
        $operator = $this->canaryOperator();
        $countryId = DB::table('countries')->insertGetId(['name' => 'Россия', 'сodeISO' => 'RU']);
        $regionId = DB::table('regions')->insertGetId([
            'name' => 'Санкт-Петербург', 'country_id' => $countryId, 'use_for_yandex_direct' => true,
        ]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Санкт-Петербург', 'region_id' => $regionId]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи',
            'eng' => 'Broccoli',
            'is_published' => true,
        ]);
        $job = app(FindBuyersDraftOrchestrator::class)->create([
            'source_type' => 'product',
            'source_id' => $product->id,
            'idempotency_key' => (string) Str::uuid(),
            'country_id' => $countryId,
            'region_id' => $regionId,
            'city_id' => $cityId,
            'limits' => [
                'max_queries' => 1,
                'max_results_per_query' => 10,
                'max_domains' => 3,
                'max_page_fetch_attempts' => 3,
                'max_candidates' => 1,
            ],
        ], $operator)->job;
        app(BuildFindBuyersQueryPlan::class)->handle($job, $operator);
        app(SubmitFindBuyersPlanForReview::class)->handle($job->fresh(), $operator);
        $job = app(ProspectingSearchJobService::class)->approve($job->fresh(), $operator);
        app(ApproveProspectingQueryPlan::class)->handle($job, $operator);

        return $job->fresh();
    }

    private function canaryOperator(): User
    {
        $permissions = [
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.prospecting.view',
            'ai_sales.prospecting.jobs.manage',
            'ai_sales.prospecting.review',
            'ai_sales.search.plan',
            'ai_sales.search.review',
            'ai_sales.search.execute',
            'ai_sales.search.results.view',
            'ai_sales.search.research',
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }
        $operator = User::query()->create([
            'name' => 'Stage 11B Synthetic Operator',
            'email' => 'stage11b-operator@synthetic.invalid',
            'password' => Hash::make(Str::random(64)),
            'type' => 'employee',
            'status' => 'active',
            'account_type' => 'individual',
        ]);
        $operator->forceFill(['email_verified_at' => now()])->save();
        $operator->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $operator;
    }

    private function setUiPreparationFlags(): void
    {
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.find_buyers.drafts_enabled' => true,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
        ]);
    }

    private function setDefaultOffFlags(): void
    {
        $keys = [
            'enabled', 'external_calls_enabled', 'local_ru_calls_enabled',
            'external_sanitized_calls_enabled', 'provider_failover_enabled', 'web_search_enabled',
            'outreach_drafting_enabled', 'outreach_sending_enabled', 'autonomous_campaigns_enabled',
            'provider_native_tools_enabled', 'live_business_workflows_enabled',
        ];
        foreach ($keys as $key) {
            config()->set('ai-sales.'.$key, false);
        }
        foreach ([
            'dossier_enabled', 'jobs_enabled', 'candidate_import_enabled', 'auto_create_unit',
            'live_search_enabled', 'live_probe_enabled', 'query_planning_enabled',
            'search_execution_enabled', 'existing_yandex_provider_enabled', 'page_fetch_enabled',
            'auto_candidate_ingestion_enabled', 'public_research_enabled', 'scoring_enabled',
            'auto_scoring_enabled', 'ai_evidence_enabled', 'live_scoring_enabled',
        ] as $key) {
            config()->set('ai-sales.prospecting.'.$key, false);
        }
        foreach (['ui_enabled', 'drafts_enabled', 'live_execution_enabled', 'auto_research_enabled', 'auto_scoring_enabled'] as $key) {
            config()->set('ai-sales.find_buyers.'.$key, false);
        }
        config()->set([
            'ai-sales.providers.timeweb.enabled' => false,
            'ai-sales.providers.timeweb.routes.local_ru.enabled' => false,
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled' => false,
            'ai-sales.providers.timeweb.probe.enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    private function bindRepositoryState(): FakeGitRepositoryStateInspector
    {
        $hash = str_repeat('c', 40);
        $fake = new FakeGitRepositoryStateInspector(new GitRepositoryState(
            branch: FindBuyersCanaryRepositoryGuard::EXPECTED_BRANCH,
            head: $hash,
            baseIsAncestor: true,
            commitsAfterBase: [[
                'hash' => $hash,
                'subject' => FindBuyersCanaryRepositoryGuard::STAGE_11B_SUBJECT,
            ]],
            stagedChanges: 0,
            modifiedChanges: 0,
            untrackedChanges: 0,
        ));
        app()->instance(GitRepositoryStateInspectorInterface::class, $fake);

        return $fake;
    }
}
