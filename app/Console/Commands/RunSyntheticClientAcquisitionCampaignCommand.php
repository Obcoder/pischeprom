<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Campaigns\AdvanceClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\AutonomousOutreachDraftPolicy;
use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignBudgetGuard;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignMetrics;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignService;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignStateMachine;
use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\SyntheticClientAcquisitionCampaignStageProcessor;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\CommunicationSuppressionService;
use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Infrastructure\AiSales\Search\FakeSearchProvider;
use App\Models\ClientAcquisitionCampaign;
use App\Models\Entity;
use App\Models\Good;
use App\Models\OutreachDispatch;
use App\Models\OutreachDraft;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\Sending;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class RunSyntheticClientAcquisitionCampaignCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-client-acquisition-campaign';

    protected $description = 'Run and roll back the complete repository-owned Stage 14 campaign scenario';

    public function handle(): int
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver");
        $database = (string) config("database.connections.{$connectionName}.database");
        $this->line('APP_ENV='.app()->environment());
        $this->line('DB_CONNECTION='.$connectionName);
        $this->line('DB_DRIVER='.$driver);
        $this->line('DB_DATABASE='.($database === ':memory:' ? ':memory:' : basename($database)));
        if (! app()->environment(['local', 'testing']) || $connectionName !== 'sqlite' || $driver !== 'sqlite') {
            $this->error('Blocked: Stage 14 synthetic acceptance requires local/testing isolated SQLite; default MySQL is never accepted.');

            return self::FAILURE;
        }
        if ($database !== ':memory:') {
            $real = realpath($database);
            $temp = realpath(sys_get_temp_dir());
            if (! $real || ! $temp || ! str_starts_with($real, $temp.DIRECTORY_SEPARATOR)) {
                $this->error('Blocked: SQLite file must be an existing OS-temp synthetic database.');

                return self::FAILURE;
            }
        }
        if (ClientAcquisitionCampaign::query()->exists()
            || Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->exists()
            || Entity::query()->without(['buildings', 'classification', 'country'])->exists()) {
            $this->error('Blocked: synthetic database must not contain Campaign, Unit, or Entity rows.');

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->configureSyntheticRuntime();
        app()->forgetInstance(SearchProviderRegistry::class);
        app()->instance(SearchProviderRegistry::class, new SearchProviderRegistry([new FakeSearchProvider([
            ['title' => 'Stage14 broccoli buyer', 'url' => 'https://stage14-search-a.example/about', 'domain' => 'stage14-search-a.example', 'snippet' => 'Synthetic broccoli buyer in Saint Petersburg.'],
            ['title' => 'Stage14 public registry', 'url' => 'https://stage14-search-b.example/company', 'domain' => 'stage14-search-b.example', 'snippet' => 'Independent fictional company evidence.'],
        ])]));

        DB::beginTransaction();
        try {
            $actor = $this->actor();
            [$countryId, $regionId, $cityId] = $this->geography();
            $product = Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => 'Брокколи', 'eng' => 'Broccoli', 'is_published' => true,
            ]);
            $good = Good::query()->create(['name' => 'Синтетическое предложение брокколи', 'is_published' => true]);
            $good->products()->attach($product->id);

            $campaigns = app(ClientAcquisitionCampaignService::class);
            $campaign = $campaigns->create([
                'safe_name' => 'Synthetic Broccoli Buyers SPB',
                'safe_objective' => 'Find fictional broccoli buyers in Saint Petersburg using repository-owned evidence.',
                'reviewer_user_id' => $actor->id,
                'primary_product_id' => $product->id,
                'originating_good_id' => $good->id,
                'automation_mode' => 'autonomous_reviewed',
                'auto_unit_approved' => true,
                'auto_draft_approved' => true,
                'schedule_cadence' => 'manual',
                'criteria' => [
                    'country_id' => $countryId, 'region_id' => $regionId, 'city_id' => $cityId,
                    'segments' => ['archetype:food_manufacturer'], 'categories' => ['frozen vegetables'],
                    'max_domains' => 3, 'max_page_fetch_attempts' => 2, 'max_results_per_query' => 2,
                ],
                'limits' => $this->limits(),
            ], $actor);
            $campaign = $campaigns->submit($campaign, $actor);
            $campaign = $campaigns->approve($campaign, $actor);

            app()->instance(
                ClientAcquisitionCampaignStageProcessorInterface::class,
                app(SyntheticClientAcquisitionCampaignStageProcessor::class),
            );
            $starter = app(StartClientAcquisitionCampaignRun::class);
            $run = $starter->handle($campaign, $actor, '00000000-0000-4000-8000-000000000014');
            $replay = $starter->handle($campaign->fresh(), $actor, '00000000-0000-4000-8000-000000000014');
            if ((int) $run->id !== (int) $replay->id) {
                throw new \RuntimeException('Campaign run idempotency failed.');
            }
            $run = app(AdvanceClientAcquisitionCampaignRun::class)->handle($run, $actor);
            if ($run->status->value !== 'completed') {
                throw new \RuntimeException('Synthetic campaign did not complete: '.($run->safe_error_code ?: 'unknown_safe_code'));
            }

            $metrics = app(ClientAcquisitionCampaignMetrics::class)->get($campaign->fresh(), $actor);
            $queue = app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor);
            $proofs = $this->safetyProofs($campaign->fresh(), $actor);
            $entities = Entity::query()->without(['buildings', 'classification', 'country'])->count();
            $autoUnits = ProspectingCandidate::query()->where('status', 'new_unit_created')
                ->where('resolution_reason_code', 'autonomous_unit_creation_v1')->count();
            $counters = [
                'campaigns' => ClientAcquisitionCampaign::query()->count(),
                'runs' => $campaign->runLinks()->count(),
                'search_jobs' => $campaign->runLinks()->whereNotNull('prospecting_search_job_id')->count(),
                'queries' => $metrics['queries']['planned'],
                'results' => $metrics['research']['results'],
                'candidates' => $metrics['candidates']['total'],
                'units_auto_created' => $autoUnits,
                'entities' => $entities,
                'product_matches' => $metrics['product_matches'],
                'scores' => $metrics['score_snapshots'],
                'drafts' => $metrics['drafts'],
                'review_items' => count($queue),
                'emails' => 0,
                'live_http' => 0,
                'provider_sends' => 0,
            ];
            if ($counters['campaigns'] !== 1 || $counters['runs'] !== 1 || $counters['search_jobs'] < 1
                || $counters['queries'] < 1 || $counters['results'] < 1 || $counters['candidates'] < 2
                || $counters['units_auto_created'] > 1 || $entities !== 0 || $counters['product_matches'] < 1
                || $counters['scores'] < 1 || $counters['drafts'] < 1 || $counters['review_items'] < 1) {
                throw new \RuntimeException('Synthetic campaign counters violated the acceptance contract.');
            }
            Http::assertNothingSent();
            Mail::assertNothingSent();
            Queue::assertNothingPushed();
            if (Sending::query()->count() !== 0 || OutreachDispatch::query()->count() !== 0) {
                throw new \RuntimeException('Dispatch boundary was violated.');
            }
            $this->line('SAFE_COUNTERS '.collect($counters)->map(fn ($value, $key) => $key.'='.$value)->implode(' '));
            $this->line('SAFE_PROOFS '.collect($proofs)->map(fn ($value, $key) => $key.'='.($value ? 'yes' : 'no'))->implode(' '));
            $this->line('RUNTIME live_yandex=0 live_timeweb=0 external_http=0 emails=0 retries=0 failovers=0 entities=0');
            DB::rollBack();
            $this->info('Synthetic Stage 14 campaign passed; all fictional rows rolled back.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $safeCode = $exception instanceof PolicyViolation
                ? $exception->errorCode
                : 'campaign_processing_failed_safe_'.strtolower(class_basename($exception));
            $this->error('Synthetic Stage 14 campaign failed safely: '.$safeCode);

            return self::FAILURE;
        }
    }

    private function configureSyntheticRuntime(): void
    {
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.autonomous_campaigns_enabled' => true,
            'ai-sales.campaigns.enabled' => true,
            'ai-sales.campaigns.scheduler_enabled' => false,
            'ai-sales.campaigns.live_search_enabled' => false,
            'ai-sales.campaigns.live_research_enabled' => false,
            'ai-sales.campaigns.auto_ingest_enabled' => true,
            'ai-sales.campaigns.auto_create_unit_enabled' => true,
            'ai-sales.campaigns.auto_scoring_enabled' => true,
            'ai-sales.campaigns.auto_draft_enabled' => true,
            'ai-sales.campaigns.notifications_enabled' => false,
            'ai-sales.campaigns.synthetic_fixture_mode' => true,
            'ai-sales.campaigns.policies.auto_draft.minimum_product_relevance' => 0,
            'ai-sales.campaigns.policies.auto_draft.minimum_confidence' => 0,
            'ai-sales.campaigns.policies.auto_draft.minimum_prospect_priority' => 0,
            'ai-sales.campaigns.limits.scheduler_batch' => 0,
            'ai-sales.campaigns.limits.max_active_runs' => 1,
            'ai-sales.campaigns.limits.max_runs_per_day' => 5,
            'ai-sales.campaigns.limits.max_runs_per_month' => 20,
            'ai-sales.campaigns.limits.global_units_per_day' => 2,
            'ai-sales.campaigns.limits.global_units_per_month' => 10,
            'ai-sales.campaigns.limits.global_drafts_per_day' => 2,
            'ai-sales.campaigns.limits.global_drafts_per_month' => 10,
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.public_research_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.auto_create_unit' => true,
            'ai-sales.prospecting.scoring_enabled' => true,
            'ai-sales.prospecting.live_scoring_enabled' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.limits.max_retries' => 0,
            'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => true,
            'ai-sales.outreach.fake_generation_enabled' => true,
            'ai-sales.outreach.suppression_management_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.queue.connection' => 'sync',
        ]);
    }

    private function actor(): User
    {
        $actor = User::factory()->create([
            'name' => 'Stage14 Synthetic Campaign Reviewer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $permissions = [
            'ai_sales.view', 'ai_sales.sales.view', 'ai_sales.procurement.view',
            'ai_sales.unit_roles.manage', 'ai_sales.contexts.manage', 'ai_sales.aliases.manage',
            'ai_sales.observation.manage', 'ai_sales.observation.verify', 'ai_sales.audit.view',
            'ai_sales.prospecting.view', 'ai_sales.prospecting.jobs.manage', 'ai_sales.prospecting.review',
            'ai_sales.prospecting.resolve', 'ai_sales.product_matches.review', 'ai_sales.timeline.view',
            'ai_sales.search.plan', 'ai_sales.search.review', 'ai_sales.search.execute',
            'ai_sales.search.results.view', 'ai_sales.search.research', 'ai_sales.search.providers.view',
            'ai_sales.scoring.view', 'ai_sales.scoring.recalculate',
            'ai_sales.outreach.view', 'ai_sales.outreach.draft', 'ai_sales.outreach.review',
            'ai_sales.outreach.claims.review', 'ai_sales.communication_suppressions.manage',
            'ai_sales.campaigns.view', 'ai_sales.campaigns.manage', 'ai_sales.campaigns.review',
            'ai_sales.campaigns.operate', 'ai_sales.campaigns.automation.manage',
            'ai_sales.campaigns.metrics.view',
        ];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'crm']);
        }
        $admin = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'crm']);
        $admin->syncPermissions($permissions);
        $actor->assignRole($admin);

        return $actor;
    }

    private function geography(): array
    {
        $countryId = DB::table('countries')->insertGetId(['name' => 'Synthetic Russia', 'сodeISO' => 'SX']);
        $regionId = DB::table('regions')->insertGetId(['name' => 'Санкт-Петербург (synthetic)', 'country_id' => $countryId]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Санкт-Петербург (synthetic)', 'region_id' => $regionId]);

        return [$countryId, $regionId, $cityId];
    }

    private function limits(): array
    {
        return [
            'max_active_runs' => 1, 'max_runs_per_day' => 2, 'max_runs_per_month' => 10,
            'max_search_requests_per_run' => 5, 'max_search_requests_per_day' => 5, 'max_search_requests_per_month' => 25,
            'max_research_pages_per_run' => 3, 'max_candidates_per_run' => 10,
            'max_units_per_run' => 1, 'max_units_per_day' => 1, 'max_units_per_month' => 5,
            'max_drafts_per_run' => 1, 'max_drafts_per_day' => 1, 'max_drafts_per_month' => 5,
            'max_requests_per_run' => 15, 'max_requests_per_day' => 15, 'max_requests_per_month' => 60,
            'max_tokens_per_run' => 8000, 'max_tokens_per_day' => 8000, 'max_tokens_per_month' => 32000,
            'max_cost_rub_per_run' => 100, 'max_cost_rub_per_day' => 100, 'max_cost_rub_per_month' => 400,
        ];
    }

    private function safetyProofs(ClientAcquisitionCampaign $campaign, User $actor): array
    {
        $budgetBlocked = false;
        $copy = $campaign->replicate();
        $copy->setAttribute('id', $campaign->id);
        $copy->setAttribute('max_requests_per_run', 0);
        try {
            app(ClientAcquisitionCampaignBudgetGuard::class)->assertCanStart($copy);
        } catch (PolicyViolation $exception) {
            $budgetBlocked = str_contains($exception->errorCode, 'budget');
        }
        $draft = OutreachDraft::query()->firstOrFail();
        $context = UnitBusinessContext::query()->findOrFail($draft->unit_business_context_id);
        app(CommunicationSuppressionService::class)->create($actor, $context->unit, $context, [
            'scope' => 'context', 'reason' => 'do_not_contact', 'source' => 'stage14_synthetic',
            'evidence_reference' => 'repository-fixture:stage14-suppression',
            'evidence_hash' => hash('sha256', 'stage14-suppression'),
        ]);
        $suppressionBlocked = false;
        try {
            app(AutonomousOutreachDraftPolicy::class)->assertEligible($campaign, $context->fresh(), $draft->productMatch);
        } catch (PolicyViolation $exception) {
            $suppressionBlocked = $exception->errorCode === 'auto_draft_suppression_blocked';
        }
        $procurement = $context->unit->businessContexts()->create([
            'lane' => 'procurement', 'role_code' => 'prospective_supplier', 'stage' => 'researching',
            'status' => 'active', 'source' => 'stage14-dual-lane-proof', 'created_by' => $actor->id,
        ]);
        $dualLaneBlocked = false;
        try {
            app(AutonomousOutreachDraftPolicy::class)->assertEligible($campaign, $procurement, $draft->productMatch);
        } catch (PolicyViolation $exception) {
            $dualLaneBlocked = $exception->errorCode === 'auto_draft_context_or_match_blocked';
        }

        return [
            'idempotent_rerun' => $campaign->runLinks()->count() === 1,
            'duplicate_unit_domain_absent' => ProspectingCandidate::query()->where('status', 'new_unit_created')->count() === 1,
            'cancellation_supported' => app(ClientAcquisitionCampaignStateMachine::class)->canTransition(
                \App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus::Running,
                \App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus::Cancelled,
            ),
            'budget_block' => $budgetBlocked,
            'suppression_block' => $suppressionBlocked,
            'dual_lane_isolation' => $dualLaneBlocked,
            'dispatch_blocked' => ! (bool) config('ai-sales.outreach.dispatch_enabled'),
        ];
    }
}
