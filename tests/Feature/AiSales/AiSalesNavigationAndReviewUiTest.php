<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignSearchJobService;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Models\Entity;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingPublicFetch;
use App\Models\ProspectingPublicResearchRecord;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchResult;
use App\Models\ProspectingSearchUsageRecord;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

final class AiSalesNavigationAndReviewUiTest extends Stage14TestCase
{
    public function test_navigation_and_product_launcher_are_permission_gated_get_only_surfaces(): void
    {
        $this->configureUiRuntime();
        $product = $this->campaignProduct('Navigation Product');
        $guestCounts = $this->domainCounts();

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Product_02')
                ->where('auth.permissions.ai_sales.view', false));
        $this->getJson(route('Ameise.ai-sales'))->assertUnauthorized();

        $withoutPermission = $this->userWith([]);
        $this->actingAs($withoutPermission)->getJson(route('Ameise.ai-sales'))->assertForbidden();

        $actor = $this->campaignUser();
        $this->actingAs($actor)->get(route('Ameise.ai-sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/AiSales')
                ->where('auth.permissions.ai_sales.view', true)
                ->where('auth.permissions.ai_sales.review', true)
                ->where('auth.permissions.ai_sales.resolve', true));
        $this->actingAs($actor)->get(route('product.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Product_02')
                ->where('id', $product->id)
                ->where('auth.permissions.ai_sales.view', true));

        $route = app('router')->getRoutes()->getByName('Ameise.ai-sales');
        $this->assertNotNull($route);
        foreach (['auth:sanctum', 'verified', 'throttle:ai-sales-ui', 'can:ai_sales.view', 'can:ai_sales.sales.view', 'can:ai_sales.prospecting.view'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }

        $layout = file_get_contents(resource_path('js/Layouts/VerwalterLayout.vue'));
        $productPage = file_get_contents(resource_path('js/Pages/Ameise/Product_02.vue'));
        $productCard = file_get_contents(resource_path('js/Components/AiSales/ProductAiSalesCampaignCard.vue'));
        $aiSalesPage = file_get_contents(resource_path('js/Pages/Ameise/AiSales.vue'));
        $this->assertStringContainsString("route('Ameise.ai-sales')", $layout);
        $this->assertStringContainsString('v-if="canViewAiSales"', $layout);
        $this->assertSame(1, substr_count($productPage, '<ProductAiSalesCampaignCard'));
        $this->assertSame(1, substr_count($productPage, '<ProductYandexSearchCard'));
        $this->assertStringContainsString('Legacy manual Yandex search', $productPage);
        $this->assertStringContainsString('🤖 Найти покупателей', file_get_contents(resource_path('js/Components/AiSales/FindBuyersLauncher.vue')));
        foreach (['campaigns:', 'results:', 'research:', 'candidates:', 'review items:'] as $counter) {
            $this->assertStringContainsString($counter, $productCard);
        }
        foreach (['Кампании', 'На проверке', 'Кандидаты', 'Units', 'Scores', 'Черновики', 'Аудит'] as $tab) {
            $this->assertStringContainsString($tab, $aiSalesPage);
        }

        $this->assertSame($guestCounts, $this->domainCounts());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_api_read_traffic_cannot_exhaust_the_ai_sales_page_limiter(): void
    {
        $this->configureUiRuntime();
        $actor = $this->campaignUser();
        $before = $this->domainCounts();

        for ($request = 1; $request <= 30; $request++) {
            $this->actingAs($actor)
                ->getJson('/api/ai-sales/prospecting/candidates?per_page=50')
                ->assertOk();
        }

        $this->actingAs($actor)->get(route('Ameise.ai-sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/AiSales'));

        $this->assertSame($before, $this->domainCounts());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_production_shaped_candidate_has_one_review_badge_and_deep_links_without_get_mutations(): void
    {
        [$actor, $campaign, $run, $job, $candidate, $product] = $this->productionShapedReviewFixture();
        $before = $this->domainCounts();

        $this->actingAs($actor)->get(route('Ameise.ai-sales', [
            'tab' => 'review',
            'candidate' => $candidate->public_id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Ameise/AiSales'));
        $this->actingAs($actor)->get(route('product.show', $product))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Ameise/Product_02'));

        $this->actingAs($actor)->getJson('/api/ai-sales/find-buyers/dashboard?limit=25')
            ->assertOk()
            ->assertJsonPath('data.jobs.0.counts.executions.request_count', 1)
            ->assertJsonPath('data.jobs.0.counts.results.total', 10)
            ->assertJsonPath('data.jobs.0.counts.fetches.total', 3)
            ->assertJsonPath('data.jobs.0.counts.research.total', 2)
            ->assertJsonPath('data.jobs.0.counts.candidates.total', 1)
            ->assertJsonPath('data.jobs.0.candidates.0.id', $candidate->public_id)
            ->assertJsonPath(
                'data.jobs.0.candidates.0.review_url',
                '/Ameise/ai-sales?tab=review&candidate='.$candidate->public_id.'#candidate-review',
            );
        $this->actingAs($actor)->getJson('/api/ai-sales/campaigns/'.$campaign->public_id.'/progress')
            ->assertOk()
            ->assertJsonPath('data.queries.executed', 1)
            ->assertJsonPath('data.research.results', 10)
            ->assertJsonPath('data.research.domain_breakdown.0.source_url', 'https://ui-buyer-1.example/about')
            ->assertJsonPath('data.candidates.total', 1)
            ->assertJsonPath('data.review_items', 1)
            ->assertJsonPath('data.usage.yandex_requests', 1)
            ->assertJsonPath('data.usage.emails_sent', 0);
        $this->actingAs($actor)->getJson('/api/ai-sales/campaigns/'.$campaign->public_id.'/review-queue?limit=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'new_unit_review')
            ->assertJsonPath('data.0.run_id', $run->public_id)
            ->assertJsonPath('data.0.product_id', $product->id);
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/candidates?per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->public_id)
            ->assertJsonPath('data.0.status', 'new_unit_review')
            ->assertJsonPath('data.0.resolution_outcome', 'new_unit_allowed');
        $this->actingAs($actor)->getJson('/api/ai-sales/prospecting/candidates/'.$candidate->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $candidate->public_id)
            ->assertJsonPath('data.products.0.id', $product->id)
            ->assertJsonPath('data.resolved_unit', null);

        $this->assertSame(AiRunStatus::RequiresAction, $run->fresh()->status);
        $this->assertSame('candidate_ingestion_review_required', $run->fresh()->safe_error_code);
        $this->assertSame($before, $this->domainCounts());
        $this->assertSame(1, ProspectingSearchUsageRecord::query()->sum('request_count'));
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_campaign_domain_projection_rejects_non_http_or_integrity_mismatched_links(): void
    {
        [$actor, $campaign, , $job] = $this->productionShapedReviewFixture();
        $first = $job->searchResults()->where('rank', 1)->firstOrFail();
        $first->update([
            'canonical_url' => 'javascript:alert(1)',
            'url_hash' => hash('sha256', 'javascript:alert(1)'),
        ]);
        $second = $job->searchResults()->where('rank', 2)->firstOrFail();
        $second->update(['url_hash' => hash('sha256', 'integrity-mismatch')]);
        $third = $job->searchResults()->where('rank', 3)->firstOrFail();
        $third->update([
            'canonical_url' => 'https://different-domain.example/about',
            'url_hash' => hash('sha256', 'https://different-domain.example/about'),
        ]);
        $before = $this->domainCounts();

        $domains = $this->actingAs($actor)
            ->getJson('/api/ai-sales/campaigns/'.$campaign->public_id.'/progress')
            ->assertOk()
            ->json('data.research.domain_breakdown');

        $this->assertNull(collect($domains)->firstWhere('domain', 'ui-buyer-1.example')['source_url']);
        $this->assertNull(collect($domains)->firstWhere('domain', 'ui-buyer-2.example')['source_url']);
        $this->assertNull(collect($domains)->firstWhere('domain', 'ui-buyer-3.example')['source_url']);
        $this->assertSame($before, $this->domainCounts());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_candidate_actions_keep_existing_services_permissions_and_lane_idor(): void
    {
        $this->configureUiRuntime();
        $actor = $this->prospectingUser(['sales', 'procurement']);
        $salesCandidate = $this->candidate($this->approvedJob($actor), $actor);
        app(ResolveProspectingCandidate::class)->evaluate($salesCandidate, $actor);
        $procurementCandidate = $this->candidate($this->approvedJob($actor, 'supplier_discovery'), $actor);
        $salesViewer = $this->userWith([
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.prospecting.view',
        ]);
        $beforeUnits = Unit::query()->count();
        $beforeEntities = Entity::query()->count();

        $this->actingAs($salesViewer)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$salesCandidate->public_id.'/create-unit')
            ->assertForbidden();
        $this->actingAs($salesViewer)
            ->getJson('/api/ai-sales/prospecting/candidates/'.$procurementCandidate->public_id)
            ->assertForbidden();
        $this->actingAs($salesViewer)
            ->postJson('/api/ai-sales/prospecting/candidates/'.$procurementCandidate->public_id.'/evaluate')
            ->assertForbidden();

        foreach (['resolve-existing', 'create-unit', 'reject'] as $action) {
            $route = app('router')->getRoutes()->getByName('api.ai-sales.prospecting.candidates.'.$action);
            $this->assertNotNull($route);
            foreach (['auth:sanctum', 'verified', 'throttle:ai-sales'] as $middleware) {
                $this->assertContains($middleware, $route->gatherMiddleware());
            }
        }
        $resolveRoute = app('router')->getRoutes()->getByName('api.ai-sales.prospecting.candidates.resolve-existing');
        $this->assertStringContainsString('ProspectingCandidateController@resolveExisting', $resolveRoute->getActionName());
        $controller = file_get_contents(app_path('Http/Controllers/API/AiSales/ProspectingCandidateController.php'));
        $resolver = file_get_contents(app_path('Domain/AiSales/Services/ResolveProspectingCandidate.php'));
        $candidateCard = file_get_contents(resource_path('js/Components/AiSales/CandidateReviewCard.vue'));
        $this->assertStringContainsString('$service->enrichExisting(', $controller);
        $this->assertStringContainsString('$decision = $this->resolver->evaluate($locked);', $resolver);
        $this->assertStringContainsString('/resolve-existing', $candidateCard);
        $this->assertStringContainsString('/create-unit', $candidateCard);
        $this->assertStringContainsString('/reject', $candidateCard);
        $this->assertStringNotContainsString('/entities', $candidateCard);

        $this->assertSame($beforeUnits, Unit::query()->count());
        $this->assertSame($beforeEntities, Entity::query()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** @return array{\App\Models\User, \App\Models\ClientAcquisitionCampaign, \App\Models\AiAgentRun, \App\Models\ProspectingSearchJob, ProspectingCandidate, \App\Models\Product} */
    private function productionShapedReviewFixture(): array
    {
        $this->configureUiRuntime();
        $actor = $this->campaignUser();
        $product = $this->campaignProduct('Production-shaped Product');
        $campaign = $this->approvedCampaign($actor, $product, [
            'automation_mode' => 'assisted',
            'limits' => array_replace($this->campaignLimits(), [
                'max_active_runs' => 1,
                'max_runs_per_day' => 1,
                'max_runs_per_month' => 1,
                'max_search_requests_per_run' => 1,
                'max_search_requests_per_day' => 1,
                'max_search_requests_per_month' => 1,
                'max_research_pages_per_run' => 3,
                'max_candidates_per_run' => 5,
                'max_units_per_run' => 0,
                'max_units_per_day' => 0,
                'max_units_per_month' => 0,
                'max_drafts_per_run' => 0,
                'max_drafts_per_day' => 0,
                'max_drafts_per_month' => 0,
            ]),
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)
            ->handle($campaign, $actor, 'ui-navigation-production-shaped');
        $job = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);
        app(PlanProspectingQueries::class)->handle($job, $actor);
        $query = app(ApproveProspectingQueryPlan::class)->handle($job, $actor)->sole();
        $execution = ProspectingSearchExecution::query()->create([
            'prospecting_search_job_id' => $job->id,
            'prospecting_search_query_id' => $query->id,
            'initiated_by' => $actor->id,
            'profile_code' => 'prospecting_b2b_discovery',
            'provider_code' => 'existing_yandex',
            'request_hash' => hash('sha256', 'ui-navigation-request'),
            'plan_hash' => $query->plan_hash,
            'status' => 'completed',
            'attempt' => 1,
            'request_count' => 1,
            'result_count' => 10,
            'duplicate_count' => 0,
            'blocked_result_count' => 0,
            'safe_request_id' => 'ui-navigation-yandex-1',
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
        ProspectingSearchUsageRecord::query()->create([
            'prospecting_search_execution_id' => $execution->id,
            'provider_code' => 'existing_yandex',
            'profile_code' => 'prospecting_b2b_discovery',
            'request_count' => 1,
            'result_count' => 10,
            'estimated_cost_rub' => 0,
            'safe_request_id' => 'ui-navigation-yandex-1',
            'recorded_at' => now(),
        ]);
        $query->update(['status' => 'completed', 'result_count' => 10]);

        $results = collect();
        foreach (range(1, 10) as $rank) {
            $url = "https://ui-buyer-{$rank}.example/about";
            $result = ProspectingSearchResult::query()->create([
                'prospecting_search_execution_id' => $execution->id,
                'prospecting_search_job_id' => $job->id,
                'prospecting_search_query_id' => $query->id,
                'rank' => $rank,
                'result_type' => 'organic',
                'title' => "Synthetic UI result {$rank}",
                'snippet' => 'Repository-owned bounded evidence.',
                'url' => $url,
                'canonical_url' => $url,
                'url_hash' => hash('sha256', $url),
                'registrable_domain' => "ui-buyer-{$rank}.example",
                'domain_hash' => hash('sha256', "ui-buyer-{$rank}.example"),
                'result_hash' => hash('sha256', "ui-navigation-result-{$rank}"),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'fetch_status' => $rank <= 2 ? 'completed' : ($rank === 3 ? 'blocked' : 'not_requested'),
                'research_status' => $rank <= 2 ? 'completed' : 'not_requested',
            ]);
            $results->push($result);
            if ($rank <= 2) {
                ProspectingPublicFetch::query()->create([
                    'prospecting_search_result_id' => $result->id,
                    'status' => 'completed',
                    'final_url' => $url,
                    'final_url_hash' => hash('sha256', $url),
                    'registrable_domain' => "ui-buyer-{$rank}.example",
                    'content_type' => 'text/html',
                    'byte_count' => 256,
                    'content_hash' => hash('sha256', "ui-navigation-page-{$rank}"),
                    'trust_level' => 'untrusted',
                    'instruction_authority' => 'none',
                    'robots_status' => 'allowed',
                    'fetched_at' => now(),
                ]);
                ProspectingPublicResearchRecord::query()->create([
                    'prospecting_search_result_id' => $result->id,
                    'workflow_code' => 'public_company_research.v1',
                    'workflow_version' => 'stage09-v1',
                    'workflow_hash' => hash('sha256', 'ui-navigation-workflow'),
                    'status' => 'completed',
                    'input_hash' => hash('sha256', "ui-navigation-input-{$rank}"),
                    'output_hash' => hash('sha256', "ui-navigation-output-{$rank}"),
                    'schema_valid' => true,
                    'safe_summary' => 'Fictional bounded company research.',
                    'provider_code' => 'fake',
                    'completed_at' => now(),
                ]);
            } elseif ($rank === 3) {
                ProspectingPublicFetch::query()->create([
                    'prospecting_search_result_id' => $result->id,
                    'status' => 'blocked',
                    'trust_level' => 'untrusted',
                    'instruction_authority' => 'none',
                    'error_category' => 'content_policy',
                    'error_code' => 'page_dtd_blocked',
                ]);
            }
        }

        $candidate = app(ProspectingCandidateService::class)->createFixture($job, [
            'working_name' => 'Synthetic UI Candidate',
            'website' => 'https://ui-candidate.example',
            'location' => 'Synthetic location',
            'public_activity_summary' => 'Fictional public activity.',
            'relevance_summary' => 'Fictional Product relevance.',
            'confidence_components' => ['relevance' => 88, 'identity' => 80],
            'product_ids' => [$product->id],
            'sources' => [[
                'type' => 'synthetic_fixture',
                'reference' => 'repository-fixture:ui-navigation',
                'title' => 'Synthetic public evidence',
                'excerpt' => 'Repository-owned bounded evidence.',
            ]],
        ], $actor, true, $query);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $candidate = $candidate->fresh();
        $results->first()->update(['prospecting_candidate_id' => $candidate->id]);

        $run->steps()->update([
            'status' => 'completed',
            'completed_at' => now(),
            'retry_count' => 0,
            'failover_count' => 0,
        ]);
        $run->steps()->where('sequence', 7)->update([
            'status' => 'requires_action',
            'completed_at' => null,
            'safe_error_code' => 'candidate_ingestion_review_required',
            'safe_error_summary' => 'Protected manual Candidate review is required.',
        ]);
        $run->update([
            'status' => 'requires_action',
            'current_step' => 7,
            'accumulated_searches' => 1,
            'safe_error_code' => 'candidate_ingestion_review_required',
            'safe_error_summary' => 'Protected manual Candidate review is required.',
        ]);

        return [$actor, $campaign->fresh(), $run->fresh(), $job->fresh(), $candidate->fresh(), $product];
    }

    private function configureUiRuntime(): void
    {
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.autonomous_campaigns_enabled' => true,
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.find_buyers.drafts_enabled' => true,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.find_buyers.auto_research_enabled' => false,
            'ai-sales.find_buyers.auto_scoring_enabled' => false,
            'ai-sales.campaigns.enabled' => true,
            'ai-sales.campaigns.scheduler_enabled' => false,
            'ai-sales.campaigns.live_search_enabled' => true,
            'ai-sales.campaigns.live_research_enabled' => true,
            'ai-sales.campaigns.auto_ingest_enabled' => false,
            'ai-sales.campaigns.auto_create_unit_enabled' => false,
            'ai-sales.campaigns.auto_scoring_enabled' => false,
            'ai-sales.campaigns.auto_draft_enabled' => false,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.public_research_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    /** @return array<string, int> */
    private function domainCounts(): array
    {
        return [
            'campaigns' => DB::table('ai_sales_campaigns')->count(),
            'runs' => DB::table('ai_agent_runs')->count(),
            'jobs' => DB::table('prospecting_search_jobs')->count(),
            'executions' => DB::table('prospecting_search_executions')->count(),
            'results' => DB::table('prospecting_search_results')->count(),
            'fetches' => DB::table('prospecting_public_fetches')->count(),
            'research' => DB::table('prospecting_public_research_records')->count(),
            'candidates' => ProspectingCandidate::query()->count(),
            'units' => Unit::query()->count(),
            'entities' => Entity::query()->count(),
            'emails' => DB::table('emails')->count(),
        ];
    }
}
