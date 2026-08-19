<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentRun;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;

final class ClientAcquisitionCampaignBudgetGuard
{
    public function assertCanStart(ClientAcquisitionCampaign $campaign): void
    {
        $globalActive = (int) config('ai-sales.campaigns.limits.max_active_runs', 0);
        $globalDaily = (int) config('ai-sales.campaigns.limits.max_runs_per_day', 0);
        $globalMonthly = (int) config('ai-sales.campaigns.limits.max_runs_per_month', 0);
        if ($globalActive < 1 || $globalDaily < 1 || $globalMonthly < 1
            || $campaign->max_active_runs < 1 || $campaign->max_runs_per_day < 1
            || $campaign->max_runs_per_month < 1 || $campaign->max_requests_per_run < 1
            || $campaign->max_requests_per_day < 1 || $campaign->max_requests_per_month < 1
            || $campaign->max_tokens_per_run < 1 || $campaign->max_tokens_per_day < 1
            || $campaign->max_tokens_per_month < 1 || (float) $campaign->max_cost_rub_per_run <= 0
            || (float) $campaign->max_cost_rub_per_day <= 0 || (float) $campaign->max_cost_rub_per_month <= 0) {
            throw new PolicyViolation('campaign_budget_missing', 'Campaign budgets are missing or zero.');
        }
        if ($campaign->max_active_runs > $globalActive
            || $campaign->max_runs_per_day > $globalDaily
            || $campaign->max_runs_per_month > $globalMonthly) {
            throw new PolicyViolation('campaign_budget_exceeds_global', 'Campaign run limits exceed code-owned global ceilings.');
        }
        $runs = ClientAcquisitionCampaignRunLink::query()->where('ai_sales_campaign_id', $campaign->id);
        if ((clone $runs)->where('created_at', '>=', now()->startOfDay())->count() >= $campaign->max_runs_per_day
            || (clone $runs)->where('created_at', '>=', now()->startOfMonth())->count() >= $campaign->max_runs_per_month) {
            throw new PolicyViolation('campaign_run_budget_exhausted', 'Campaign run budget is exhausted.');
        }
        $activeStatuses = ['queued', 'preparing', 'policy_check', 'ready', 'requires_action', 'processing'];
        $campaignActive = ClientAcquisitionCampaignRunLink::query()
            ->where('ai_sales_campaign_id', $campaign->id)
            ->whereHas('run', fn ($query) => $query->whereIn('status', $activeStatuses))->count();
        $globalActiveCount = AiAgentRun::query()
            ->where('definition_code', ClientAcquisitionCampaignWorkflowRegistry::CODE)
            ->whereIn('status', $activeStatuses)->count();
        if ($campaignActive >= $campaign->max_active_runs || $globalActiveCount >= $globalActive) {
            throw new PolicyViolation('campaign_active_run_limit', 'Campaign active-run limit is reached.');
        }

        $runIds = (clone $runs)->pluck('ai_agent_run_id');
        $day = AiAgentRun::query()->whereIn('id', $runIds)->where('created_at', '>=', now()->startOfDay());
        $month = AiAgentRun::query()->whereIn('id', $runIds)->where('created_at', '>=', now()->startOfMonth());
        if ((int) (clone $day)->sum('accumulated_searches') >= (int) $campaign->max_requests_per_day
            || (int) (clone $month)->sum('accumulated_searches') >= (int) $campaign->max_requests_per_month
            || (int) (clone $day)->sum('accumulated_tokens') >= (int) $campaign->max_tokens_per_day
            || (int) (clone $month)->sum('accumulated_tokens') >= (int) $campaign->max_tokens_per_month
            || (float) (clone $day)->sum('accumulated_cost_rub') >= (float) $campaign->max_cost_rub_per_day
            || (float) (clone $month)->sum('accumulated_cost_rub') >= (float) $campaign->max_cost_rub_per_month) {
            throw new PolicyViolation('campaign_aggregate_budget_exhausted', 'Campaign day or month budget is exhausted.');
        }
    }

    public function assertRunWithinBudget(ClientAcquisitionCampaign $campaign, AiAgentRun $run): void
    {
        $runIds = $campaign->runLinks()->pluck('ai_agent_run_id');
        $day = AiAgentRun::query()->whereIn('id', $runIds)->where('created_at', '>=', now()->startOfDay());
        $month = AiAgentRun::query()->whereIn('id', $runIds)->where('created_at', '>=', now()->startOfMonth());
        if ((int) $run->accumulated_searches > (int) $campaign->max_search_requests_per_run
            || (int) $run->accumulated_searches > (int) $campaign->max_requests_per_run
            || (int) $run->accumulated_tokens > (int) $campaign->max_tokens_per_run
            || (float) $run->accumulated_cost_rub > (float) $campaign->max_cost_rub_per_run
            || (int) (clone $day)->sum('accumulated_searches') > (int) $campaign->max_search_requests_per_day
            || (int) (clone $month)->sum('accumulated_searches') > (int) $campaign->max_search_requests_per_month
            || (int) (clone $day)->sum('accumulated_searches') > (int) $campaign->max_requests_per_day
            || (int) (clone $month)->sum('accumulated_searches') > (int) $campaign->max_requests_per_month
            || (int) (clone $day)->sum('accumulated_tokens') > (int) $campaign->max_tokens_per_day
            || (int) (clone $month)->sum('accumulated_tokens') > (int) $campaign->max_tokens_per_month
            || (float) (clone $day)->sum('accumulated_cost_rub') > (float) $campaign->max_cost_rub_per_day
            || (float) (clone $month)->sum('accumulated_cost_rub') > (float) $campaign->max_cost_rub_per_month) {
            throw new PolicyViolation('campaign_run_budget_exceeded', 'Campaign run exceeded a frozen budget.');
        }
    }
}
