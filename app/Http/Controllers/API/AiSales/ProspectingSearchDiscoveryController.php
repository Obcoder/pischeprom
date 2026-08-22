<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Campaigns\ResumeClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Prospecting\CandidateIngestionReviewProjection;
use App\Domain\AiSales\Prospecting\ResultBusinessRoleClassifier;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ExecuteProspectingSearchJob;
use App\Domain\AiSales\Services\IngestProspectingSearchCandidate;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\RebuildProspectingQueryPlan;
use App\Domain\AiSales\Web\SafePublicPageFetcher;
use App\Domain\AiSales\Workflows\PublicCompanyResearchWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\RebuildProspectingQueryPlanRequest;
use App\Http\Requests\AiSales\SearchDiscoveryActionRequest;
use App\Http\Resources\AiSales\ProspectingCandidateResource;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProspectingSearchDiscoveryController extends Controller
{
    public function providers(
        Request $request,
        ProspectingFeatureGuard $features,
        ProspectingAuthorizationService $authorization,
    ): JsonResponse {
        $features->queryPlanning();
        $allowed = collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->contains(fn (BusinessLane $lane): bool => $authorization->can(
                $request->user(),
                ProspectingAuthorizationService::VIEW_SEARCH_PROVIDERS,
                $lane,
            ));
        abort_unless($allowed, 403);

        return response()->json(['data' => [[
            'code' => 'existing_yandex',
            'source_of_truth' => 'App\\Services\\YandexSearchService',
            'profiles' => ['product_page_search', 'prospecting_b2b_discovery'],
            'configured' => filled(config('services.yandex_search.api_key'))
                && filled(config('services.yandex_search.folder_id')),
            'server_side_credentials' => true,
            'fallback_allowed' => false,
        ]]]);
    }

    public function plan(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        PlanProspectingQueries $service,
    ): JsonResponse {
        Gate::authorize('view', $prospectingSearchJob);
        $queries = $service->handle($prospectingSearchJob, $request->user());

        return response()->json(['data' => $this->queries($queries)], 201);
    }

    public function approvePlan(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        ApproveProspectingQueryPlan $service,
        ResumeClientAcquisitionCampaignRun $campaignRuns,
    ): JsonResponse {
        Gate::authorize('review', $prospectingSearchJob);
        $queries = $service->handle($prospectingSearchJob, $request->user());
        $resumeQueued = $campaignRuns->afterQueryPlanApproval($prospectingSearchJob->fresh());

        return response()->json([
            'data' => $this->queries($queries),
            'meta' => ['campaign_resume_queued' => $resumeQueued],
        ]);
    }

    public function rebuildPlan(
        RebuildProspectingQueryPlanRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        RebuildProspectingQueryPlan $service,
    ): JsonResponse {
        Gate::authorize('review', $prospectingSearchJob);
        $queries = $service->handle($prospectingSearchJob, $request->validated(), $request->user());

        return response()->json([
            'data' => $this->queries($queries),
            'meta' => [
                'query_count' => $queries->count(),
                'external_http' => 0,
                'execution_started' => false,
            ],
        ]);
    }

    public function execute(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        ExecuteProspectingSearchJob $service,
    ): JsonResponse {
        Gate::authorize('view', $prospectingSearchJob);
        $queryIds = $service->handle($prospectingSearchJob, $request->user());

        return response()->json(['data' => [
            'status' => 'dispatched',
            'dispatched_query_count' => count($queryIds),
            'retries' => 0,
            'failover_allowed' => false,
        ]], 202);
    }

    public function index(
        Request $request,
        ProspectingSearchJob $prospectingSearchJob,
        ProspectingFeatureGuard $features,
        ProspectingAuthorizationService $authorization,
        ResultBusinessRoleClassifier $businessRoles,
        CandidateIngestionReviewProjection $candidateReviews,
    ): JsonResponse {
        $features->queryPlanning();
        Gate::authorize('view', $prospectingSearchJob);
        $authorization->authorize(
            $request->user(),
            ProspectingAuthorizationService::VIEW_SEARCH_RESULTS,
            $prospectingSearchJob->lane,
        );
        $queries = $prospectingSearchJob->queries()->whereNotNull('plan_hash')
            ->where('plan_status', '!=', 'stale')
            ->with(['executions.usage'])
            ->orderBy('sequence')->get();
        $results = $prospectingSearchJob->searchResults()
            ->with([
                'candidate:id,public_id',
                'searchQuery:id,geography',
                'publicFetch:id,prospecting_search_result_id,status,registrable_domain,page_title,meta_description,headings,text_excerpt,channel_count,robots_status,error_category,error_code,fetched_at',
                'research:id,prospecting_search_result_id,status,safe_summary,activity_mentions,location_hints,product_mentions,schema_valid,error_category,error_code,completed_at',
            ])
            ->orderBy('rank')->limit(250)->get();

        return response()->json(['data' => [
            'queries' => $this->queries($queries),
            'results' => $results->map(fn (ProspectingSearchResult $result): array => [
                'id' => $result->public_id,
                'rank' => $result->rank,
                'title' => $result->title,
                'snippet' => $result->snippet,
                'url' => $result->canonical_url,
                'domain' => $result->registrable_domain,
                'duplicate' => $result->duplicate_of_id !== null,
                'trust_level' => $result->trust_level,
                'instruction_authority' => $result->instruction_authority,
                'fetch' => $result->publicFetch ? [
                    'status' => $result->publicFetch->status,
                    'title' => $result->publicFetch->page_title,
                    'description' => $result->publicFetch->meta_description,
                    'channel_count' => $result->publicFetch->channel_count,
                    'robots_status' => $result->publicFetch->robots_status,
                    'error_category' => $result->publicFetch->error_category,
                    'error_code' => $result->publicFetch->error_code,
                ] : null,
                'research' => $result->research ? [
                    'status' => $result->research->status,
                    'summary' => $result->research->safe_summary,
                    'schema_valid' => $result->research->schema_valid,
                    'error_category' => $result->research->error_category,
                    'error_code' => $result->research->error_code,
                ] : null,
                'candidate_id' => $result->candidate?->public_id,
                'buyer_classification' => $businessRoles->classify($result, $prospectingSearchJob->lane)->safeArray(),
            ])->all(),
            'budgets' => [
                'max_queries' => $prospectingSearchJob->max_queries,
                'max_results_per_query' => $prospectingSearchJob->max_results_per_query,
                'max_search_requests_per_job' => config('ai-sales.prospecting.limits.max_search_requests_per_job'),
                'retries' => 0,
                'failovers' => 0,
            ],
            'candidate_ingestion_review' => $candidateReviews->forJob($prospectingSearchJob, $results),
        ]]);
    }

    public function fetch(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchResult $prospectingSearchResult,
        SafePublicPageFetcher $fetcher,
    ): JsonResponse {
        $fetch = $fetcher->fetch($prospectingSearchResult, $request->user());

        return response()->json(['data' => [
            'status' => $fetch->status,
            'domain' => $fetch->registrable_domain,
            'content_type' => $fetch->content_type,
            'byte_count' => $fetch->byte_count,
            'channel_count' => $fetch->channel_count,
            'trust_level' => $fetch->trust_level,
            'instruction_authority' => $fetch->instruction_authority,
            'robots_status' => $fetch->robots_status,
        ]]);
    }

    public function research(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchResult $prospectingSearchResult,
        PublicCompanyResearchWorkflow $workflow,
    ): JsonResponse {
        $record = $workflow->execute($prospectingSearchResult, $request->user());

        return response()->json(['data' => [
            'status' => $record->status,
            'workflow_code' => $record->workflow_code,
            'workflow_version' => $record->workflow_version,
            'schema_valid' => $record->schema_valid,
            'summary' => $record->safe_summary,
            'provider_code' => $record->provider_code,
            'native_tools' => false,
        ]]);
    }

    public function ingestCandidate(
        SearchDiscoveryActionRequest $request,
        ProspectingSearchResult $prospectingSearchResult,
        IngestProspectingSearchCandidate $service,
        ResumeClientAcquisitionCampaignRun $campaignRuns,
    ): JsonResponse {
        $candidate = $service->handle($prospectingSearchResult, $request->user());
        $resumeQueued = $campaignRuns->afterCandidateIngestion($prospectingSearchResult->job()->firstOrFail());

        return response()->json([
            'data' => (new ProspectingCandidateResource($candidate))->resolve($request),
            'campaign_resume_queued' => $resumeQueued,
            'unit_created' => false,
            'entity_created' => false,
            'entity_linked' => false,
        ], 201);
    }

    private function queries($queries): array
    {
        return $queries->map(fn ($query): array => [
            'id' => $query->id,
            'sequence' => $query->sequence,
            'template_code' => $query->template_code,
            'template_version' => $query->template_version,
            'plan_hash' => $query->plan_hash,
            'plan_status' => $query->plan_status,
            'query' => $query->safe_display_query,
            'language' => $query->language,
            'geography' => $query->geography,
            'industry_intent' => $query->industry_intent,
            'status' => $query->status,
            'result_count' => $query->result_count,
            'candidate_count' => $query->candidate_count,
            'executions' => $query->relationLoaded('executions') ? $query->executions->map(fn ($execution): array => [
                'id' => $execution->public_id,
                'status' => $execution->status,
                'profile' => $execution->profile_code,
                'provider' => $execution->provider_code,
                'request_count' => $execution->request_count,
                'result_count' => $execution->result_count,
                'duplicate_count' => $execution->duplicate_count,
                'blocked_result_count' => $execution->blocked_result_count,
                'duration_ms' => $execution->duration_ms,
                'error_category' => $execution->error_category,
                'error_code' => $execution->error_code,
                'estimated_cost_rub' => $execution->usage?->estimated_cost_rub,
            ])->all() : [],
        ])->all();
    }
}
