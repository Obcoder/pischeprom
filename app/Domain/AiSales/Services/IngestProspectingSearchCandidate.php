<?php

namespace App\Domain\AiSales\Services;

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

        $sourceHost = mb_strtolower((string) parse_url($result->canonical_url, PHP_URL_HOST));
        $sameDomainResults = $result->job->searchResults()
            ->where('domain_hash', $result->domain_hash)
            ->whereNull('duplicate_of_id')
            ->with('publicFetch')
            ->orderBy('rank')
            ->limit(100)
            ->get()
            ->filter(fn (ProspectingSearchResult $item): bool => hash_equals(
                $sourceHost,
                mb_strtolower((string) parse_url($item->canonical_url, PHP_URL_HOST)),
            ))
            ->take(20)
            ->values();
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
        $channels = collect($result->publicFetch->protected_channels ?? [])->map(fn (array $channel): array => [
            'kind' => $channel['kind'],
            'value' => $channel['value'],
            'contact_role' => $channel['contact_role'],
            'communication_state' => 'review_required',
        ])->take(20)->all();
        $productIds = $result->job->products()
            ->wherePivotIn('role', ['primary', 'additional'])
            ->pluck('products.id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $activity = $result->research?->safe_summary
            ?: $result->publicFetch->meta_description
            ?: mb_substr((string) $result->publicFetch->text_excerpt, 0, 1000);
        $evidenceHash = hash('sha256', $sameDomainResults->pluck('result_hash')->sort()->implode('|'));

        $candidate = $this->candidates->createFromSearchResult($result->job, [
            'working_name' => $result->publicFetch->page_title ?: $result->title ?: $result->registrable_domain,
            'website' => $result->publicFetch->final_url,
            'location_display' => $result->searchQuery->geography,
            'public_activity_summary' => mb_substr((string) $activity, 0, 1000),
            'relevance_summary' => 'Public Product-first search evidence requires human review; no Stage 10 score was calculated.',
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
