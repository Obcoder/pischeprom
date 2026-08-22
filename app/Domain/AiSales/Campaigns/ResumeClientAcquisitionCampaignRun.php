<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchJob;

final class ResumeClientAcquisitionCampaignRun
{
    public function __construct(
        private readonly ClientAcquisitionCampaignHashes $hashes,
        private readonly ClientAcquisitionCampaignBudgetGuard $budgets,
    ) {}

    public function afterQueryPlanApproval(ProspectingSearchJob $job): bool
    {
        $queries = $job->queries()->whereNotNull('plan_hash')->get(['id', 'plan_hash', 'plan_status']);
        if ($queries->isEmpty()
            || $queries->contains(fn ($query): bool => $query->plan_status !== 'approved')
            || $queries->pluck('plan_hash')->unique()->count() !== 1) {
            return false;
        }

        return $this->dispatchLinkedRun($job, 'query_plan_review_required');
    }

    public function afterSearchBatchSettled(ProspectingSearchJob $job): bool
    {
        $queryIds = $job->queries()->where('plan_status', 'approved')->pluck('id');
        if ($queryIds->isEmpty()) {
            return false;
        }
        $settled = ProspectingSearchExecution::query()
            ->where('prospecting_search_job_id', $job->id)
            ->whereIn('prospecting_search_query_id', $queryIds)
            ->whereIn('status', ['completed', 'failed'])
            ->distinct()
            ->count('prospecting_search_query_id');
        if ($settled !== $queryIds->count()) {
            return false;
        }

        return $this->dispatchLinkedRun($job, 'search_jobs_dispatched');
    }

    public function afterCandidateIngestion(ProspectingSearchJob $job): bool
    {
        if (! $job->candidates()->exists()) {
            return false;
        }

        return $this->dispatchLinkedRun($job, [
            'public_research_review_required',
            'candidate_ingestion_review_required',
        ]);
    }

    /** @param string|list<string> $expectedReason */
    private function dispatchLinkedRun(ProspectingSearchJob $job, string|array $expectedReason): bool
    {
        $link = ClientAcquisitionCampaignRunLink::query()
            ->with(['run', 'campaign'])
            ->where('prospecting_search_job_id', $job->id)
            ->first();
        $run = $link?->run;
        $campaign = $link?->campaign;
        $expectedReasons = (array) $expectedReason;
        if (! $run || ! $campaign
            || $run->status !== AiRunStatus::RequiresAction
            || ! collect($expectedReasons)->contains(
                fn (string $reason): bool => hash_equals($reason, (string) $run->safe_error_code),
            )
            || $campaign->status !== ClientAcquisitionCampaignStatus::Running
            || ! $this->hashes->isCurrent($campaign)
            || $run->initiator_user_id === null) {
            return false;
        }

        try {
            $this->budgets->assertExecutionSlotAvailable($run);
        } catch (PolicyViolation $exception) {
            if ($exception->errorCode === 'campaign_active_run_limit') {
                return false;
            }

            throw $exception;
        }

        ExecuteClientAcquisitionCampaignRunJob::dispatch($run->id, $run->initiator_user_id)->afterCommit();

        return true;
    }
}
