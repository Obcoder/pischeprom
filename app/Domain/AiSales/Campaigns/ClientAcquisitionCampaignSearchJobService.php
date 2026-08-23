<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\AiAgentRun;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ClientAcquisitionCampaignSearchJobService
{
    public function __construct(private readonly ProspectingSearchJobService $jobs) {}

    public function ensure(ClientAcquisitionCampaign $campaign, AiAgentRun $run): ProspectingSearchJob
    {
        $link = ClientAcquisitionCampaignRunLink::query()->where('ai_agent_run_id', $run->id)->firstOrFail();
        if ($link->prospecting_search_job_id) {
            return ProspectingSearchJob::query()->findOrFail($link->prospecting_search_job_id);
        }
        $owner = User::query()->findOrFail($campaign->owner_user_id);
        $reviewer = User::query()->findOrFail($campaign->approved_by);
        $products = $campaign->products()->get(['products.id'])->groupBy('pivot.role');
        $criteria = $campaign->criteria_snapshot ?? [];
        $job = DB::transaction(function () use ($campaign, $run, $link, $owner, $reviewer, $products, $criteria): ProspectingSearchJob {
            $job = $this->jobs->createDraft([
                'purpose' => 'buyer_discovery',
                'safe_objective' => $campaign->safe_objective,
                'primary_product_id' => $products->get('primary')->first()->id,
                'additional_product_ids' => $products->get('additional', collect())->pluck('id')->all(),
                'excluded_product_ids' => $products->get('exclude', collect())->pluck('id')->all(),
                'originating_good_ids' => $campaign->originating_good_id ? [$campaign->originating_good_id] : [],
                'explicit_good_product_selection' => $campaign->originating_good_id !== null,
                'country_id' => $criteria['country_id'] ?? null,
                'region_id' => $criteria['region_id'] ?? null,
                'city_id' => $criteria['city_id'] ?? null,
                'locale' => 'ru-RU',
                'max_queries' => max(1, min((int) $campaign->max_search_requests_per_run, 20)),
                'max_candidates' => max(1, (int) $campaign->max_candidates_per_run),
                'max_results_per_query' => max(1, min(50, (int) ($criteria['max_results_per_query'] ?? 10))),
                'max_rows' => max(1, min(1000, (int) $campaign->max_candidates_per_run)),
                'max_bytes' => 1_048_576,
                'criteria' => $criteria,
            ], $owner, [
                'max_domains' => min(
                    (int) ($criteria['max_domains'] ?? 0),
                    (int) config('ai-sales.campaigns.limits.max_domains_per_run', 0),
                ),
                'max_page_fetch_attempts' => min(
                    (int) $campaign->max_research_pages_per_run,
                    (int) ($criteria['max_page_fetch_attempts'] ?? 0),
                    (int) config('ai-sales.campaigns.limits.max_research_pages_per_run', 0),
                ),
            ]);
            $job->update([
                'launch_source_type' => 'campaign',
                'launch_source_id' => $campaign->id,
                'wizard_version' => (string) config('ai-sales.campaigns.wizard_version', 'stage14-campaign-v1'),
                'disclosure_policy_hash' => $campaign->disclosure_policy_hash,
                'draft_idempotency_key_hash' => hash('sha256', 'campaign-run:'.$run->public_id),
                'policy_version' => $campaign->policy_version,
                'workflow_version' => $campaign->workflow_code,
                'ai_agent_run_id' => $run->id,
            ]);
            $job = $this->jobs->submit($job->fresh(), $owner);
            $job = $this->jobs->approve($job->fresh(), $reviewer);
            $link->update(['prospecting_search_job_id' => $job->id]);

            return $job;
        }, 3);

        return $job->fresh(['products', 'goods', 'queries']);
    }
}
