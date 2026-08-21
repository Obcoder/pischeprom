<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignHashes;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchJob;
use Illuminate\Validation\ValidationException;

final class ProspectingResearchBudget
{
    public function __construct(private readonly ClientAcquisitionCampaignHashes $campaignHashes) {}

    /** @return array{source: string, current: bool, pages_limit: int, pages_used: int, pages_remaining: int, domains_limit: int, domains_used: int, domains_remaining: int} */
    public function snapshot(ProspectingSearchJob $job): array
    {
        [$source, $current, $pageLimit, $domainLimit] = $this->limits($job);
        $pagesUsed = $job->searchResults()->whereHas('publicFetch')->count();
        $domainsUsed = $job->searchResults()->whereHas('publicFetch')
            ->distinct()->count('domain_hash');

        return [
            'source' => $source,
            'current' => $current,
            'pages_limit' => $pageLimit,
            'pages_used' => $pagesUsed,
            'pages_remaining' => max(0, $pageLimit - $pagesUsed),
            'domains_limit' => $domainLimit,
            'domains_used' => $domainsUsed,
            'domains_remaining' => max(0, $domainLimit - $domainsUsed),
        ];
    }

    public function assertCanFetch(ProspectingSearchJob $job, string $domainHash): void
    {
        $budget = $this->snapshot($job);
        if (! $budget['current']) {
            throw ValidationException::withMessages([
                'research' => 'campaign_research_approval_stale',
            ]);
        }
        if ($budget['pages_remaining'] < 1) {
            throw ValidationException::withMessages([
                'research' => 'public_research_page_budget_exhausted',
            ]);
        }

        $domainAlreadyReserved = $job->searchResults()
            ->where('domain_hash', $domainHash)->whereHas('publicFetch')->exists();
        if (! $domainAlreadyReserved && $budget['domains_remaining'] < 1) {
            throw ValidationException::withMessages([
                'research' => 'public_research_domain_budget_exhausted',
            ]);
        }
    }

    /** @return array{string, bool, int, int} */
    private function limits(ProspectingSearchJob $job): array
    {
        $link = ClientAcquisitionCampaignRunLink::query()
            ->with('campaign')
            ->where('prospecting_search_job_id', $job->id)
            ->first();
        $campaign = $link?->campaign;
        if ($job->launch_source_type === 'campaign' && $campaign
            && (int) $job->launch_source_id === (int) $campaign->id) {
            $current = $campaign->status === ClientAcquisitionCampaignStatus::Running
                && is_string($link->approval_snapshot_hash)
                && is_string($campaign->approval_snapshot_hash)
                && hash_equals($campaign->approval_snapshot_hash, $link->approval_snapshot_hash)
                && $this->campaignHashes->isCurrent($campaign);
            $criteria = $campaign->criteria_snapshot ?? [];
            $pageLimit = $this->positiveMinimum([
                (int) $campaign->max_research_pages_per_run,
                (int) ($criteria['max_page_fetch_attempts'] ?? 0),
                (int) config('ai-sales.campaigns.limits.max_research_pages_per_run', 0),
            ]);
            $domainLimit = $this->positiveMinimum([
                (int) ($criteria['max_domains'] ?? 0),
                (int) config('ai-sales.campaigns.limits.max_domains_per_run', 0),
            ]);

            return ['campaign', $current, $pageLimit, $domainLimit];
        }

        return [
            'prospecting_job',
            true,
            $this->positiveMinimum([
                (int) ($job->criteria_snapshot['max_page_fetch_attempts']
                    ?? config('ai-sales.find_buyers.limits.max_page_fetch_attempts', 0)),
                (int) config('ai-sales.find_buyers.limits.max_page_fetch_attempts', 0),
            ]),
            $this->positiveMinimum([
                (int) ($job->criteria_snapshot['max_domains']
                    ?? config('ai-sales.find_buyers.limits.max_domains', 0)),
                (int) config('ai-sales.find_buyers.limits.max_domains', 0),
            ]),
        ];
    }

    /** @param list<int> $values */
    private function positiveMinimum(array $values): int
    {
        if (collect($values)->contains(fn (int $value): bool => $value < 1)) {
            return 0;
        }

        return min($values);
    }
}
