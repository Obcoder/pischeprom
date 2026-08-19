<?php

namespace App\Domain\AiSales\Campaigns;

use App\Models\AiAgentRun;
use App\Models\AiUsageRecord;
use App\Models\ClientAcquisitionCampaign;
use App\Models\OutreachDraft;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchQuery;
use App\Models\ProspectingSearchResult;
use App\Models\ProspectingSearchUsageRecord;
use App\Models\UnitProductMatch;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\User;

final class ClientAcquisitionCampaignMetrics
{
    public function __construct(
        private readonly ClientAcquisitionCampaignAuthorizationService $authorization,
        private readonly CampaignReviewQueue $reviews,
    ) {}

    /** @return array<string, mixed> */
    public function get(ClientAcquisitionCampaign $campaign, User $actor): array
    {
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::VIEW_METRICS);
        if (! $this->authorization->canAccess($actor, $campaign)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Campaign metrics are not authorized.');
        }
        $jobIds = $campaign->runLinks()->whereNotNull('prospecting_search_job_id')->pluck('prospecting_search_job_id');
        $runIds = $campaign->runLinks()->pluck('ai_agent_run_id');
        $candidate = ProspectingCandidate::query()->whereIn('prospecting_search_job_id', $jobIds);
        $match = UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->whereIn('prospecting_search_job_id', $jobIds));
        $draft = OutreachDraft::query()->whereHas('productMatch.candidateProduct.candidate', fn ($query) => $query
            ->whereIn('prospecting_search_job_id', $jobIds));
        $snapshots = UnitProductRelevanceSnapshot::query()->whereIn('unit_product_match_id', (clone $match)->select('unit_product_matches.id'));
        $searchUsage = ProspectingSearchUsageRecord::query()->whereIn(
            'prospecting_search_execution_id',
            ProspectingSearchExecution::query()->whereIn('prospecting_search_job_id', $jobIds)->select('id'),
        );
        $aiUsage = AiUsageRecord::query()->whereIn('ai_agent_run_id', $runIds);
        $reviews = $this->reviews->forCampaign($campaign, $actor, 200);

        return [
            'campaign_id' => $campaign->public_id,
            'runs' => [
                'started' => AiAgentRun::query()->whereIn('id', $runIds)->count(),
                'completed' => AiAgentRun::query()->whereIn('id', $runIds)->where('status', 'completed')->count(),
                'blocked' => AiAgentRun::query()->whereIn('id', $runIds)->whereIn('status', ['failed', 'budget_exceeded', 'blocked_by_policy', 'blocked_by_dlp', 'blocked_by_contour', 'provider_unavailable'])->count(),
            ],
            'queries' => [
                'planned' => ProspectingSearchQuery::query()->whereIn('prospecting_search_job_id', $jobIds)->count(),
                'executed' => ProspectingSearchExecution::query()->whereIn('prospecting_search_job_id', $jobIds)->where('status', 'completed')->count(),
            ],
            'research' => [
                'results' => ProspectingSearchResult::query()->whereIn('prospecting_search_job_id', $jobIds)->count(),
                'unique_domains' => ProspectingSearchResult::query()->whereIn('prospecting_search_job_id', $jobIds)->whereNull('duplicate_of_id')->distinct()->count('domain_hash'),
                'fetch_completed' => ProspectingSearchResult::query()->whereIn('prospecting_search_job_id', $jobIds)->where('fetch_status', 'completed')->count(),
                'fetch_blocked' => ProspectingSearchResult::query()->whereIn('prospecting_search_job_id', $jobIds)->where('fetch_status', 'blocked')->count(),
            ],
            'candidates' => [
                'total' => (clone $candidate)->count(),
                'exact' => (clone $candidate)->where('resolution_outcome', 'exact_existing')->count(),
                'probable' => (clone $candidate)->where('resolution_outcome', 'probable_existing_review')->count(),
                'new' => (clone $candidate)->where('resolution_outcome', 'new_unit_allowed')->count(),
                'units_created' => (clone $candidate)->where('status', 'new_unit_created')->count(),
                'units_enriched' => (clone $candidate)->where('status', 'existing_unit_enriched')->count(),
            ],
            'product_matches' => (clone $match)->count(),
            'score_snapshots' => (clone $snapshots)->count(),
            'score_bands' => (clone $snapshots)->selectRaw('band, COUNT(*) as aggregate')
                ->groupBy('band')->pluck('aggregate', 'band')->map(fn ($value) => (int) $value)->all(),
            'drafts' => (clone $draft)->count(),
            'review_items' => count($reviews),
            'review_categories' => collect($reviews)->countBy('category')->all(),
            'blocks' => [
                'policy' => collect($reviews)->where('category', 'policy_block')->count(),
                'dlp' => collect($reviews)->filter(fn (array $item) => str_contains($item['reason_code'], 'dlp'))->count(),
                'budget' => collect($reviews)->where('category', 'budget_block')->count(),
                'provider' => collect($reviews)->where('category', 'provider_error')->count(),
            ],
            'usage' => [
                'yandex_requests' => (int) (clone $searchUsage)->sum('request_count'),
                'yandex_cost_rub' => (float) (clone $searchUsage)->sum('estimated_cost_rub'),
                'timeweb_requests' => (clone $aiUsage)->where('provider', 'timeweb')->count(),
                'tokens' => (int) AiAgentRun::query()->whereIn('id', $runIds)->sum('accumulated_tokens'),
                'cost_rub' => (float) AiAgentRun::query()->whereIn('id', $runIds)->sum('accumulated_cost_rub'),
                'emails_sent' => 0,
            ],
        ];
    }
}
