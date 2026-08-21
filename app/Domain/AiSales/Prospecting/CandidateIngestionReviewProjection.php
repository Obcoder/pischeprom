<?php

namespace App\Domain\AiSales\Prospecting;

use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class CandidateIngestionReviewProjection
{
    public function __construct(
        private readonly ResultBusinessRoleClassifier $roles,
        private readonly PublicCompanyIdentityResolver $identities,
        private readonly ProspectingResearchBudget $budgets,
    ) {}

    /**
     * @param  EloquentCollection<int, ProspectingSearchResult>|Collection<int, ProspectingSearchResult>  $results
     * @return array<string, mixed>
     */
    public function forJob(ProspectingSearchJob $job, EloquentCollection|Collection $results): array
    {
        $budget = $this->budgets->snapshot($job);
        $domains = $results->whereNull('duplicate_of_id')
            ->groupBy('domain_hash')
            ->map(fn (Collection $domainResults): array => $this->domain($job, $domainResults, $budget))
            ->sortBy(fn (array $domain): string => sprintf(
                '%02d|%03d|%010d',
                $domain['action_priority'],
                100 - (int) $domain['buyer_classification']['confidence'],
                (int) $domain['source']['rank'],
            ))
            ->take(max(1, $budget['domains_limit']))
            ->values();

        return [
            'job_id' => $job->public_id,
            'status' => $job->status->value,
            'budget' => $budget,
            'counts' => [
                'results' => $results->count(),
                'unique_domains' => $results->whereNull('duplicate_of_id')->pluck('domain_hash')->unique()->count(),
                'buyer_like_domains' => $domains->whereIn('buyer_classification.role', ['potential_buyer', 'possible_buyer'])->count(),
                'candidate_ready_domains' => $domains->where('next_action', 'ingest_candidate')->count(),
                'research_actions' => $domains->whereIn('next_action', ['fetch', 'research'])->count(),
            ],
            'domains' => $domains->map(fn (array $domain): array => collect($domain)
                ->except('action_priority')->all())->all(),
        ];
    }

    /** @param Collection<int, ProspectingSearchResult> $domainResults
     * @param  array<string, mixed>  $budget
     * @return array<string, mixed>
     */
    private function domain(ProspectingSearchJob $job, Collection $domainResults, array $budget): array
    {
        /** @var ProspectingSearchResult $first */
        $first = $domainResults->sortBy('rank')->first();
        $domainResults = $domainResults->filter(fn (ProspectingSearchResult $result): bool => hash_equals(
            (string) $first->registrable_domain,
            (string) $result->registrable_domain,
        ))->values();
        $combinedEvidence = $domainResults->map(fn (ProspectingSearchResult $result): string => implode(' ', array_filter([
            $result->title, $result->snippet, $result->publicFetch?->page_title,
            $result->publicFetch?->meta_description, $result->publicFetch?->text_excerpt,
            ...((array) ($result->publicFetch?->headings ?? [])),
            $result->research?->safe_summary,
            ...((array) ($result->research?->activity_mentions ?? [])),
        ])))->implode(' ');
        $role = $this->roles->classifyEvidence($combinedEvidence, (string) $first->registrable_domain, $job->lane);
        $identity = $this->identities->resolve($domainResults);
        $candidate = $domainResults->pluck('candidate')->filter()->first();
        $completed = $domainResults->first(fn (ProspectingSearchResult $result): bool => $this->completedFetch($result));
        $researchable = $domainResults->first(fn (ProspectingSearchResult $result): bool => $this->completedFetch($result)
            && $result->research === null);
        $unfetched = $domainResults->sortBy(fn (ProspectingSearchResult $result): string => sprintf(
            '%d|%010d',
            $this->pagePriority((string) parse_url((string) $result->canonical_url, PHP_URL_PATH)),
            (int) $result->rank,
        ))->first(fn (ProspectingSearchResult $result): bool => $result->publicFetch === null);
        $domainReserved = $domainResults->contains(fn (ProspectingSearchResult $result): bool => $result->publicFetch !== null);
        $canIngest = $candidate === null && $completed !== null && $role->candidateEligible && $identity->resolved();
        $canResearch = ! $canIngest && $researchable !== null && $role->researchEligible;
        $canFetch = ! $canIngest && ! $canResearch && $unfetched !== null && $role->researchEligible
            && $budget['current'] && $budget['pages_remaining'] > 0
            && ($domainReserved || $budget['domains_remaining'] > 0);
        [$nextAction, $actionResult, $reason, $priority] = match (true) {
            $candidate !== null => ['open_candidate', null, 'candidate_already_created', 0],
            $canIngest => ['ingest_candidate', $completed, 'buyer_identity_ready', 0],
            $canResearch => ['research', $researchable, 'completed_fetch_requires_safe_research', 1],
            $canFetch => ['fetch', $unfetched, 'bounded_public_fetch_required', 2],
            ! $role->researchEligible => ['none', null, 'buyer_role_not_research_eligible', 5],
            ! $budget['current'] => ['none', null, 'campaign_research_approval_stale', 4],
            $budget['pages_remaining'] < 1 => ['none', null, 'public_research_page_budget_exhausted', 4],
            default => ['none', null, $this->blockedReason($domainResults, $role, $identity), 3],
        };
        $source = $actionResult ?: $completed ?: $unfetched ?: $first;

        return [
            'domain' => $first->registrable_domain,
            'buyer_classification' => $role->safeArray(),
            'identity' => [
                'working_name' => $identity->workingName,
                'confidence' => $identity->confidence,
                'status' => $identity->evidenceStatus,
                'activity_summary' => $identity->activitySummary,
                'geography' => $identity->geography,
            ],
            'source' => [
                'result_id' => $source->public_id,
                'title' => $source->publicFetch?->page_title ?: $source->title,
                'url' => $source->canonical_url,
                'rank' => (int) $source->rank,
                'fetch_status' => $source->publicFetch?->status ?? $source->fetch_status,
                'fetch_error_code' => $source->publicFetch?->error_code,
                'research_status' => $source->research?->status ?? $source->research_status,
                'research_error_code' => $source->research?->error_code,
                'research_summary' => $source->research?->safe_summary,
            ],
            'candidate_id' => $candidate?->public_id,
            'next_action' => $nextAction,
            'reason_code' => $reason,
            'action_priority' => $priority,
        ];
    }

    private function completedFetch(ProspectingSearchResult $result): bool
    {
        return $result->publicFetch?->status === 'completed'
            && hash_equals((string) $result->registrable_domain, (string) $result->publicFetch->registrable_domain);
    }

    /** @param Collection<int, ProspectingSearchResult> $results */
    private function blockedReason(Collection $results, ResultBusinessRoleDecision $role, CompanyIdentityEnvelope $identity): string
    {
        $fetchError = $results->pluck('publicFetch.error_code')->filter()->first();
        if (is_string($fetchError) && $fetchError !== '') {
            return $fetchError;
        }
        if ($role->candidateEligible && ! $identity->resolved()) {
            return 'identity_unresolved_review_required';
        }

        return $role->candidateEligible ? 'completed_public_evidence_required' : 'buyer_role_not_candidate_eligible';
    }

    private function pagePriority(string $path): int
    {
        $path = mb_strtolower(trim($path, '/'));
        if ($path === '') {
            return 0;
        }
        if (preg_match('~(^|/)(about|company|o-kompanii|о-компании)(/|$)~u', $path)) {
            return 1;
        }
        if (preg_match('~(^|/)(contact|contacts|kontakty|контакты)(/|$)~u', $path)) {
            return 2;
        }

        return 3;
    }
}
