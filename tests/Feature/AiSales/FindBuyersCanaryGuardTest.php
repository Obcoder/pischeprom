<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryEnvironmentGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryJobGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryRepositoryGuard;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Domain\AiSales\FindBuyers\SubmitFindBuyersPlanForReview;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Infrastructure\AiSales\Probes\RealGitRepositoryStateInspector;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AiSales\FakeGitRepositoryStateInspector;

class FindBuyersCanaryGuardTest extends Stage11TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRepositoryStates')]
    public function test_invalid_repository_state_is_blocked_without_environment_bypass(
        array $overrides,
        string $safeCode,
    ): void {
        $fake = new FakeGitRepositoryStateInspector($this->repositoryState($overrides));
        $guard = new FindBuyersCanaryRepositoryGuard($fake);

        try {
            $guard->assertExpectedWorktree();
            $this->fail('Invalid repository state must be blocked.');
        } catch (SearchProviderException $exception) {
            $this->assertSame($safeCode, $exception->safeCode);
        }

        $this->assertSame(1, $fake->inspectCalls);
        Http::assertNothingSent();
    }

    public function test_clean_expected_commit_state_passes_and_testing_keeps_real_runtime_inspector(): void
    {
        $fake = new FakeGitRepositoryStateInspector($this->repositoryState());
        $this->assertSame('clean_committed_stage11b', (new FindBuyersCanaryRepositoryGuard($fake))->assertExpectedWorktree());
        $this->assertInstanceOf(
            RealGitRepositoryStateInspector::class,
            app(GitRepositoryStateInspectorInterface::class),
        );
        $this->assertStringStartsWith('Tests\\', FakeGitRepositoryStateInspector::class);

        foreach ([
            app_path('Domain/AiSales/FindBuyers/Canary/FindBuyersCanaryRepositoryGuard.php'),
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

    public function test_wrong_database_and_default_mysql_are_blocked_without_connecting_mysql(): void
    {
        $guard = app(FindBuyersCanaryEnvironmentGuard::class);

        config()->set([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
        ]);
        try {
            $guard->assertEnvironmentAndDatabase();
            $this->fail('Default MySQL must be blocked.');
        } catch (SearchProviderException $exception) {
            $this->assertSame('stage11b_file_sqlite_required', $exception->safeCode);
        }
        $this->assertArrayNotHasKey('mysql', DB::getConnections());

        config()->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        try {
            $guard->assertEnvironmentAndDatabase();
            $this->fail('In-memory SQLite must be blocked for live canary.');
        } catch (SearchProviderException $exception) {
            $this->assertSame('stage11b_file_sqlite_required', $exception->safeCode);
        }
    }

    public function test_current_approved_product_and_good_jobs_pass_closed_world_revalidation(): void
    {
        [$productJob] = $this->approvedCanaryJob(false);
        $productContext = app(FindBuyersCanaryJobGuard::class)->validate($productJob);
        $this->assertSame('Брокколи', $productContext->productName);
        $this->assertSame('product', $productContext->launchSourceType);
        $this->assertSame(1, $productContext->caps['yandex_search_requests']);
        $this->assertSame(10, $productContext->caps['normalized_results']);
        $this->assertSame(3, $productContext->caps['fetch_domains']);

        [$goodJob] = $this->approvedCanaryJob(true);
        $goodContext = app(FindBuyersCanaryJobGuard::class)->validate($goodJob);
        $this->assertSame('good', $goodContext->launchSourceType);
        $this->assertSame(1, $goodContext->originatingGoodCount);
        Http::assertNothingSent();
    }

    public function test_unapproved_cancelled_and_stale_disclosure_jobs_are_blocked(): void
    {
        [$unapproved] = $this->approvedCanaryJob(false);
        $unapproved->update(['status' => 'review_required', 'approved_by' => null, 'approved_at' => null]);
        $this->assertJobGuardCode($unapproved->fresh(), 'stage11b_approved_job_required');

        [$cancelled] = $this->approvedCanaryJob(false);
        $cancelled->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->assertJobGuardCode($cancelled->fresh(), 'stage11b_approved_job_required');

        [$disclosure] = $this->approvedCanaryJob(false);
        $disclosure->update(['disclosure_policy_hash' => str_repeat('f', 64)]);
        $this->assertJobGuardCode($disclosure->fresh(), 'stage11b_disclosure_hash_stale');
    }

    public function test_stale_product_plan_good_mapping_and_excess_caps_are_blocked(): void
    {
        [$productJob, , $product] = $this->approvedCanaryJob(false);
        $product->update(['is_published' => false]);
        $this->assertJobGuardCode($productJob->fresh(), 'stage11b_single_published_primary_product_required');

        [$planJob] = $this->approvedCanaryJob(false);
        $planJob->queries()->firstOrFail()->update(['plan_hash' => str_repeat('e', 64)]);
        $this->assertJobGuardCode($planJob->fresh(), 'stage11b_approved_plan_stale');

        [$goodJob, , , $good] = $this->approvedCanaryJob(true);
        $other = $this->product('Другой синтетический Product');
        $good->products()->attach($other->id);
        $this->assertJobGuardCode($goodJob->fresh(), 'stage11b_good_product_mapping_stale');

        [$capsJob] = $this->approvedCanaryJob(false);
        $capsJob->update(['max_results_per_query' => 11]);
        $this->assertJobGuardCode($capsJob->fresh(), 'stage11b_job_caps_exceeded');
    }

    public function test_revoked_operator_permission_is_blocked(): void
    {
        [$job, $operator] = $this->approvedCanaryJob(false);
        $operator->revokePermissionTo('ai_sales.search.execute', 'crm');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->expectException(AuthorizationException::class);
        app(FindBuyersCanaryJobGuard::class)->validate($job->fresh());
    }

    public function test_find_buyers_job_cannot_use_browser_search_execute_route_or_button(): void
    {
        [$job, $operator] = $this->approvedCanaryJob(false);
        Bus::fake();
        config()->set([
            'ai-sales.web_search_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
        ]);

        $this->actingAs($operator)
            ->postJson('/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search-execute', [])
            ->assertForbidden();
        Bus::assertNothingDispatched();
        Http::assertNothingSent();

        $this->actingAs($operator)
            ->getJson('/api/ai-sales/prospecting/jobs/'.$job->public_id)
            ->assertOk()
            ->assertJsonPath('data.find_buyers.live_execution_allowed', false)
            ->assertJsonPath('data.execution_available', false);

        $panel = file_get_contents(resource_path('js/Components/Unit/AiSales/ProspectingReviewPanel.vue'));
        $this->assertIsString($panel);
        $this->assertStringContainsString('v-if="!job.find_buyers?.wizard_version"', $panel);
    }

    public function test_canary_command_exposes_only_job_and_fixed_control_options(): void
    {
        $command = Artisan::all()['ai-sales:run-find-buyers-canary'];
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('job'));
        foreach (['dry-run', 'live', 'yes', 'retain-db'] as $allowed) {
            $this->assertTrue($definition->hasOption($allowed));
        }
        foreach (['query', 'url', 'provider', 'model', 'contour', 'product', 'good', 'max-results', 'max-http'] as $blocked) {
            $this->assertFalse($definition->hasOption($blocked));
        }
    }

    /** @return array{0: ProspectingSearchJob, 1: User, 2: Product, 3: ?Good} */
    private function approvedCanaryJob(bool $fromGood): array
    {
        $operator = $this->prospectingUser();
        $product = $this->product('Брокколи');
        $good = null;
        $countryId = DB::table('countries')->insertGetId(['name' => 'Россия', 'сodeISO' => 'RU']);
        $regionId = DB::table('regions')->insertGetId([
            'name' => 'Санкт-Петербург', 'country_id' => $countryId, 'use_for_yandex_direct' => true,
        ]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Санкт-Петербург', 'region_id' => $regionId]);
        $sourceType = 'product';
        $sourceId = $product->id;
        if ($fromGood) {
            $good = Good::query()->create(['name' => 'Брокколи canary Good', 'is_published' => true]);
            $good->products()->attach($product->id);
            $sourceType = 'good';
            $sourceId = $good->id;
        }
        $draft = app(FindBuyersDraftOrchestrator::class)->create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'selected_product_id' => $product->id,
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
        app(BuildFindBuyersQueryPlan::class)->handle($draft, $operator);
        app(SubmitFindBuyersPlanForReview::class)->handle($draft->fresh(), $operator);
        $approved = app(ProspectingSearchJobService::class)->approve($draft->fresh(), $operator);
        app(ApproveProspectingQueryPlan::class)->handle($approved, $operator);

        return [$approved->fresh(), $operator->fresh(), $product->fresh(), $good?->fresh()];
    }

    private function product(string $name): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => $name,
            'eng' => null,
            'is_published' => true,
        ]);
    }

    private function assertJobGuardCode(ProspectingSearchJob $job, string $safeCode): void
    {
        try {
            app(FindBuyersCanaryJobGuard::class)->validate($job);
            $this->fail('Invalid canary Job must be blocked.');
        } catch (SearchProviderException $exception) {
            $this->assertSame($safeCode, $exception->safeCode);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function repositoryState(array $overrides = []): GitRepositoryState
    {
        $hash = str_repeat('b', 40);
        $values = array_replace([
            'branch' => FindBuyersCanaryRepositoryGuard::EXPECTED_BRANCH,
            'head' => $hash,
            'baseIsAncestor' => true,
            'commitsAfterBase' => [[
                'hash' => $hash,
                'subject' => FindBuyersCanaryRepositoryGuard::STAGE_11B_SUBJECT,
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

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidRepositoryStates(): iterable
    {
        $firstHash = str_repeat('a', 40);
        $secondHash = str_repeat('b', 40);
        $subject = FindBuyersCanaryRepositoryGuard::STAGE_11B_SUBJECT;

        yield 'modified' => [['modifiedChanges' => 1], 'stage11b_modified_changes_blocked'];
        yield 'staged' => [['stagedChanges' => 1], 'stage11b_staged_changes_blocked'];
        yield 'untracked' => [['untrackedChanges' => 1], 'stage11b_untracked_changes_blocked'];
        yield 'wrong branch' => [['branch' => 'feature/wrong'], 'stage11b_branch_mismatch'];
        yield 'Stage 11 not ancestor' => [
            ['baseIsAncestor' => false, 'commitsAfterBase' => []],
            'stage11b_stage11_not_ancestor',
        ];
        yield 'extra commit' => [[
            'head' => $secondHash,
            'commitsAfterBase' => [
                ['hash' => $firstHash, 'subject' => $subject],
                ['hash' => $secondHash, 'subject' => 'unexpected'],
            ],
        ], 'stage11b_commit_count_invalid'];
        yield 'wrong subject' => [[
            'commitsAfterBase' => [['hash' => $firstHash, 'subject' => 'wrong']],
        ], 'stage11b_commit_subject_invalid'];
        yield 'head mismatch' => [['head' => $firstHash], 'stage11b_head_not_canary_commit'];
    }
}
