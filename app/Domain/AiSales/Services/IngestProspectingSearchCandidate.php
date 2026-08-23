<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Prospecting\ProductRelevanceEvidenceComposer;
use App\Domain\AiSales\Prospecting\PublicCompanyIdentityResolver;
use App\Domain\AiSales\Prospecting\ResultBusinessRoleClassifier;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngestProspectingSearchCandidate
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly ProspectingCandidateService $candidates,
        private readonly ResultBusinessRoleClassifier $roles,
        private readonly PublicCompanyIdentityResolver $identities,
        private readonly ProductRelevanceEvidenceComposer $relevance,
    ) {}

    public function handle(ProspectingSearchResult $result, User $actor): ProspectingCandidate
    {
        $this->features->candidateImport();
        $result->loadMissing(['job', 'searchQuery', 'publicFetch', 'research']);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::RESEARCH_SEARCH_RESULTS, $result->job->lane);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $result->job->lane);
        if ($result->duplicate_of_id !== null || ! $result->publicFetch
            || $result->publicFetch->status !== 'completed'
            || $result->publicFetch->registrable_domain !== $result->registrable_domain) {
            throw ValidationException::withMessages([
                'search_result' => 'Candidate ingestion requires non-duplicate same-domain public-site evidence; directory-only evidence is insufficient.',
            ]);
        }

        $sameDomainResults = $result->job->searchResults()
            ->where('domain_hash', $result->domain_hash)
            ->whereNull('duplicate_of_id')
            ->with(['publicFetch', 'research', 'searchQuery'])
            ->orderBy('rank')
            ->limit(100)
            ->get()
            ->filter(fn (ProspectingSearchResult $item): bool => hash_equals(
                (string) $result->registrable_domain,
                (string) $item->registrable_domain,
            ))
            ->take(20)
            ->values();
        $existingCandidateId = $sameDomainResults->pluck('prospecting_candidate_id')->filter()->unique()->first();
        if ($existingCandidateId) {
            return ProspectingCandidate::query()->findOrFail($existingCandidateId)
                ->load(['job.goods', 'sources', 'channels', 'unitMatches', 'products.product']);
        }
        $combinedEvidence = $sameDomainResults->map(fn (ProspectingSearchResult $item): string => implode(' ', array_filter([
            $item->title, $item->snippet, $item->publicFetch?->page_title,
            $item->publicFetch?->meta_description, $item->publicFetch?->text_excerpt,
            ...((array) ($item->publicFetch?->headings ?? [])),
            $item->research?->safe_summary,
            ...((array) ($item->research?->activity_mentions ?? [])),
        ])))->implode(' ');
        $role = $this->roles->classifyEvidence($combinedEvidence, (string) $result->registrable_domain, $result->job->lane);
        if (! $role->candidateEligible) {
            throw ValidationException::withMessages([
                'search_result' => 'buyer_role_not_candidate_eligible:'.$role->role->value,
            ]);
        }
        $identity = $this->identities->resolve($sameDomainResults);
        if (! $identity->resolved()) {
            throw ValidationException::withMessages([
                'search_result' => 'identity_unresolved_review_required',
            ]);
        }
        $sources = $sameDomainResults->map(function (ProspectingSearchResult $item): array {
            $excerpt = $item->publicFetch?->text_excerpt ?: $item->snippet;

            return [
                'type' => 'public_search',
                'url' => $item->canonical_url,
                'reference' => 'search-result:'.$item->result_hash,
                'title' => $item->publicFetch?->page_title ?: $item->title,
                'excerpt' => mb_substr((string) $excerpt, 0, 1000),
                'accessed_at' => $item->publicFetch?->fetched_at ?? $item->created_at,
            ];
        })->all();
        if ($sources === []) {
            throw ValidationException::withMessages(['search_result' => 'At least one fetched public source is required.']);
        }
        $channels = $sameDomainResults->flatMap(fn (ProspectingSearchResult $item) => $item->publicFetch?->protected_channels ?? [])
            ->unique(fn (array $channel): string => ($channel['kind'] ?? '').'|'.($channel['value'] ?? ''))
            ->map(fn (array $channel): array => [
                'kind' => $channel['kind'],
                'value' => $channel['value'],
                'contact_role' => $channel['contact_role'],
                'communication_state' => 'review_required',
            ])->take(20)->values()->all();
        $productIds = $result->job->products()
            ->wherePivotIn('role', ['primary', 'additional'])
            ->pluck('products.id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $evidence = $this->relevance->compose($sameDomainResults, $role);
        $evidenceHash = hash('sha256', $sameDomainResults->pluck('result_hash')->sort()->implode('|'));

        $candidate = $this->candidates->createFromSearchResult($result->job, [
            'working_name' => $identity->workingName,
            'website' => $sameDomainResults->pluck('publicFetch.final_url')->filter()->first() ?: $result->canonical_url,
            'location_display' => $identity->geography ?: $result->searchQuery->geography,
            'public_activity_summary' => $identity->activitySummary,
            'relevance_summary' => $evidence['summary'].' Factors: '.implode(', ', $evidence['factors']),
            'confidence_components' => [
                'identity' => $identity->confidence,
                'relevance' => $evidence['confidence'],
                'buyer_role' => $role->confidence,
            ],
            'sources' => $sources,
            'channels' => $channels,
            'product_ids' => $productIds,
        ], $result->searchQuery, 'search-result:'.$result->result_hash, $evidenceHash);

        DB::transaction(function () use ($sameDomainResults, $candidate): void {
            foreach ($sameDomainResults as $sameDomainResult) {
                if ($sameDomainResult->prospecting_candidate_id === null) {
                    $sameDomainResult->update(['prospecting_candidate_id' => $candidate->id]);
                } elseif ((int) $sameDomainResult->prospecting_candidate_id !== (int) $candidate->id) {
                    throw ValidationException::withMessages(['candidate' => 'Search evidence is already bound to another Candidate.']);
                }
            }
        }, 3);

        return $candidate->fresh(['job.goods', 'sources', 'channels', 'unitMatches', 'products.product']);
    }
}
