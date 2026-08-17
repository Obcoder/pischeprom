<?php

namespace App\Console\Commands;

use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Domain\AiSales\FindBuyers\FindBuyersProgressQuery;
use App\Domain\AiSales\FindBuyers\SubmitFindBuyersPlanForReview;
use App\Domain\AiSales\Scoring\ProspectingScoreRecalculationService;
use App\Domain\AiSales\Search\SearchProviderRequest;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Infrastructure\AiSales\Search\FakeSearchProvider;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingPublicResearchRecord;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchResult;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RunSyntheticFindBuyersCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-find-buyers';

    protected $description = 'Run the repository-owned Product-first Find Buyers workflow in a rollback-only isolated SQLite transaction';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('Synthetic Find Buyers requires local/testing isolated SQLite.');

            return self::FAILURE;
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! $this->isIsolatedSqlite($database)) {
            $this->components->error('SQLite must be in-memory under testing or file-backed inside the OS temporary directory.');

            return self::FAILURE;
        }
        if (! $this->hasRequiredSchema()) {
            $this->components->error('Synthetic Find Buyers requires the migrated Stage 11 schema.');

            return self::FAILURE;
        }
        if ($this->containsDomainData()) {
            $this->components->error('Synthetic Find Buyers requires a fresh database with no Product/Good/Unit/Entity/Job/Candidate rows.');

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        $original = config('ai-sales');
        $summary = null;
        DB::beginTransaction();

        try {
            $this->enableSyntheticCodeOnlyFlags();
            $actor = $this->actor();
            $countryId = DB::table('countries')->insertGetId(['name' => 'Россия', 'сodeISO' => 'RU']);
            $regionId = DB::table('regions')->insertGetId([
                'name' => 'Санкт-Петербург', 'country_id' => $countryId, 'use_for_yandex_direct' => true,
            ]);
            $cityId = DB::table('cities')->insertGetId(['name' => 'Санкт-Петербург', 'region_id' => $regionId]);
            $product = Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => 'Брокколи', 'eng' => 'Broccoli', 'is_published' => true,
            ]);
            $good = Good::query()->create(['name' => 'Брокколи, синтетический Good', 'is_published' => true]);
            $good->products()->attach($product->id);

            $job = app(FindBuyersDraftOrchestrator::class)->create([
                'source_type' => 'good',
                'source_id' => $good->id,
                'selected_product_id' => $product->id,
                'idempotency_key' => '11111111-1111-4111-8111-111111111111',
                'company_activity_codes' => ['food_manufacturing'],
                'country_id' => $countryId,
                'region_id' => $regionId,
                'city_id' => $cityId,
                'limits' => [
                    'max_queries' => 2, 'max_results_per_query' => 2, 'max_domains' => 2,
                    'max_page_fetch_attempts' => 1, 'max_candidates' => 2,
                ],
            ], $actor)->job;
            app(BuildFindBuyersQueryPlan::class)->handle($job, $actor);
            $job = app(SubmitFindBuyersPlanForReview::class)->handle($job->fresh(), $actor);
            $job = app(ProspectingSearchJobService::class)->approve($job->fresh(), $actor);
            app(ApproveProspectingQueryPlan::class)->handle($job, $actor);
            $query = $job->queries()->where('plan_status', 'approved')->firstOrFail();

            $requestHash = hash('sha256', 'stage11-synthetic-search|'.$query->query_hash);
            $response = (new FakeSearchProvider)->search(new SearchProviderRequest(
                $job->public_id,
                $query->id,
                'prospecting_b2b_discovery',
                $query->safe_display_query,
                'ru-RU',
                $query->geography,
                2,
                $requestHash,
            ));
            $execution = ProspectingSearchExecution::query()->create([
                'prospecting_search_job_id' => $job->id,
                'prospecting_search_query_id' => $query->id,
                'initiated_by' => $actor->id,
                'profile_code' => $response->profileCode,
                'provider_code' => 'fake',
                'request_hash' => $requestHash,
                'plan_hash' => $query->plan_hash,
                'status' => 'completed',
                'attempt' => 1,
                'request_count' => 1,
                'result_count' => count($response->results),
                'duplicate_count' => 0,
                'blocked_result_count' => 1,
                'safe_request_id' => $response->safeRequestId,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
            $results = collect($response->results)->map(function ($item) use ($execution, $job, $query): ProspectingSearchResult {
                $canonical = $item->url;

                return ProspectingSearchResult::query()->create([
                    'prospecting_search_execution_id' => $execution->id,
                    'prospecting_search_job_id' => $job->id,
                    'prospecting_search_query_id' => $query->id,
                    'rank' => $item->rank,
                    'title' => $item->title,
                    'snippet' => $item->snippet,
                    'url' => $item->url,
                    'canonical_url' => $canonical,
                    'url_hash' => hash('sha256', $canonical),
                    'registrable_domain' => $item->domain,
                    'domain_hash' => hash('sha256', $item->domain),
                    'result_hash' => hash('sha256', $canonical.'|'.$item->rank),
                    'trust_level' => 'untrusted',
                    'instruction_authority' => 'none',
                    'fetch_status' => $item->rank === 1 ? 'completed' : 'blocked',
                    'research_status' => $item->rank === 1 ? 'completed' : 'failed',
                ]);
            });
            $primaryResult = $results->first();
            ProspectingPublicFetch::query()->create([
                'prospecting_search_result_id' => $primaryResult->id,
                'status' => 'completed',
                'final_url_hash' => hash('sha256', $primaryResult->canonical_url),
                'registrable_domain' => $primaryResult->registrable_domain,
                'content_type' => 'text/html',
                'byte_count' => 512,
                'page_title' => 'Синтетический покупатель',
                'text_excerpt' => 'Публичное синтетическое описание пищевого производителя.',
                'channel_count' => 0,
                'content_hash' => hash('sha256', 'stage11-synthetic-page'),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'robots_status' => 'allowed',
                'fetched_at' => now(),
            ]);
            ProspectingPublicResearchRecord::query()->create([
                'prospecting_search_result_id' => $primaryResult->id,
                'workflow_code' => 'public_company_research.v1',
                'workflow_version' => 'stage11-synthetic-v1',
                'workflow_hash' => hash('sha256', 'stage11-synthetic-research-workflow'),
                'status' => 'completed',
                'input_hash' => hash('sha256', 'stage11-synthetic-research-input'),
                'output_hash' => hash('sha256', 'stage11-synthetic-research-output'),
                'schema_valid' => true,
                'safe_summary' => 'Repository-owned synthetic buyer research.',
                'activity_mentions' => ['food_manufacturing'],
                'location_hints' => ['Санкт-Петербург'],
                'product_mentions' => ['Брокколи'],
                'provider_code' => 'fake',
                'completed_at' => now(),
            ]);
            $blockedResult = $results->last();
            ProspectingPublicFetch::query()->create([
                'prospecting_search_result_id' => $blockedResult->id,
                'status' => 'blocked',
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'error_category' => 'content_policy',
                'error_code' => 'synthetic_fetch_blocked',
            ]);
            ProspectingPublicResearchRecord::query()->create([
                'prospecting_search_result_id' => $blockedResult->id,
                'workflow_code' => 'public_company_research.v1',
                'workflow_version' => 'stage11-synthetic-v1',
                'workflow_hash' => hash('sha256', 'stage11-synthetic-research-workflow'),
                'status' => 'failed',
                'input_hash' => hash('sha256', 'stage11-synthetic-blocked-input'),
                'schema_valid' => false,
                'provider_code' => 'fake',
                'error_category' => 'safe_failure',
                'error_code' => 'synthetic_research_blocked',
            ]);

            $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
                'working_name' => 'ООО Синтетический покупатель',
                'website' => 'https://buyer.synthetic.example',
                'public_activity_summary' => 'Синтетическое пищевое производство.',
                'relevance_summary' => 'Публичный синтетический сигнал использования Product Брокколи.',
                'confidence_components' => ['relevance' => 88, 'identity' => 82],
                'product_ids' => [$product->id],
                'sources' => [[
                    'type' => 'synthetic_fixture',
                    'reference' => 'repository-fixture:stage11:buyer',
                    'title' => 'Synthetic public evidence',
                    'excerpt' => 'Repository-owned bounded evidence.',
                ]],
            ], $actor, true, $query);
            $primaryResult->update(['prospecting_candidate_id' => $candidate->id]);
            $unit = app(ResolveProspectingCandidate::class)->createNewUnit($candidate, $actor);
            $context = $unit->businessContexts()->where('lane', 'sales')->firstOrFail();
            $match = $context->productMatches()->where('product_id', $product->id)->firstOrFail();
            $scoring = app(ProspectingScoreRecalculationService::class);
            $productScore = $scoring->product($actor, $match);
            $priorityScore = $scoring->priority($actor, $context);
            $progress = app(FindBuyersProgressQuery::class)->get($job->fresh(), $actor)->toArray();

            $summary = [
                'environment' => app()->environment(),
                'database_driver' => 'sqlite',
                'database' => $database === ':memory:' ? ':memory:' : basename($database),
                'fixture' => 'buyer_broccoli_spb_stage11_v1',
                'product' => 'Брокколи',
                'purpose' => 'buyer_discovery',
                'lane' => 'sales',
                'role_code' => 'prospective_customer',
                'job_status' => $job->status->value,
                'progress_stage' => $progress['stage'],
                'queries' => $progress['counts']['queries'],
                'results' => $progress['counts']['results'],
                'fetches' => $progress['counts']['fetches'],
                'candidates' => $progress['counts']['candidates'],
                'product_relevance' => ['band' => $productScore->band, 'eligibility' => $productScore->eligibility],
                'prospect_priority' => ['band' => $priorityScore->band, 'eligibility' => $priorityScore->eligibility],
                'human_review_simulated' => true,
                'unit_created_inside_rollback_only_transaction' => true,
                'entity_mutations' => 0,
                'email_actions' => 0,
                'http_requests' => 0,
                'retries' => 0,
                'failovers' => 0,
                'live_execution' => false,
            ];
        } catch (Throwable $exception) {
            $this->components->error('Synthetic Find Buyers failed safely ('.class_basename($exception).').');

            return self::FAILURE;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            config()->set('ai-sales', $original);
        }

        $summary['rolled_back'] = ! $this->containsDomainData();
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $summary['rolled_back'] ? self::SUCCESS : self::FAILURE;
    }

    private function isIsolatedSqlite(string $database): bool
    {
        if ($database === ':memory:') {
            return app()->environment('testing');
        }
        $temp = realpath(sys_get_temp_dir());
        $directory = realpath(dirname($database));

        return $temp !== false && $directory !== false
            && ($directory === $temp || str_starts_with($directory, $temp.DIRECTORY_SEPARATOR));
    }

    private function containsDomainData(): bool
    {
        foreach (['products', 'goods', 'units', 'entities', 'prospecting_search_jobs', 'prospecting_candidates'] as $table) {
            if (DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasRequiredSchema(): bool
    {
        foreach ([
            'products', 'goods', 'units', 'entities', 'prospecting_search_jobs',
            'prospecting_candidates', 'unit_product_relevance_snapshots',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return Schema::hasColumn('prospecting_search_jobs', 'wizard_version');
    }

    private function enableSyntheticCodeOnlyFlags(): void
    {
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.scoring_enabled' => true,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.find_buyers.drafts_enabled' => true,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.find_buyers.auto_research_enabled' => false,
            'ai-sales.find_buyers.auto_scoring_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
        ]);
    }

    private function actor(): User
    {
        $actor = User::query()->create([
            'name' => 'Stage 11 Synthetic Reviewer',
            'email' => 'stage11-synthetic@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(40)),
            'status' => 'active',
        ]);
        $permissions = [
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.unit_roles.manage',
            'ai_sales.contexts.manage', 'ai_sales.aliases.manage', 'ai_sales.observation.manage',
            'ai_sales.prospecting.view', 'ai_sales.prospecting.jobs.manage',
            'ai_sales.prospecting.review', 'ai_sales.prospecting.resolve',
            'ai_sales.good_matches.review', 'ai_sales.product_matches.review',
            'ai_sales.timeline.view', 'ai_sales.search.plan', 'ai_sales.search.review',
            'ai_sales.search.results.view', 'ai_sales.scoring.view', 'ai_sales.scoring.recalculate',
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }
        $actor->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor;
    }
}
