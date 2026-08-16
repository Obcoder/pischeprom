<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\CandidateProductSource;
use App\Domain\AiSales\Enums\CandidateProductStatus;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchQuery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingCandidateService
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingCandidateNormalizer $normalizer,
    ) {}

    public function createFixture(
        ProspectingSearchJob $job,
        array $input,
        User $actor,
        bool $repositoryOwnedSynthetic = false,
        ?ProspectingSearchQuery $searchQuery = null,
    ): ProspectingCandidate {
        $this->features->candidateImport();
        $this->features->assertNoLiveSearch();
        if (! app()->environment(['local', 'testing']) || ! $repositoryOwnedSynthetic) {
            throw ValidationException::withMessages(['fixture' => 'Stage 08 accepts repository-owned synthetic fixtures only.']);
        }
        if ($job->status !== ProspectingJobStatus::Approved) {
            throw ValidationException::withMessages(['job' => 'Candidate import requires a human-approved prospecting job.']);
        }
        $normalized = $this->normalizer->normalize($input, $job->purpose);
        $availableProductIds = $job->products()
            ->wherePivotIn('role', [ProductScopeRole::Primary->value, ProductScopeRole::Additional->value])
            ->pluck('products.id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($availableProductIds->isEmpty()) {
            throw ValidationException::withMessages(['products' => 'Candidate import requires an approved Product-first job scope.']);
        }
        $candidateProductIds = collect($input['product_ids'] ?? $availableProductIds->all())
            ->map(fn ($id) => (int) $id)->unique()->take(25)->values();
        if ($candidateProductIds->isEmpty() || $candidateProductIds->diff($availableProductIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['product_ids' => 'Candidate Products must be a non-empty subset of the approved Job Product scope.']);
        }
        $normalized['normalized_payload_hash'] = hash('sha256', $normalized['normalized_payload_hash'].'|'.$candidateProductIds->sort()->implode(','));

        if ($searchQuery && (int) $searchQuery->prospecting_search_job_id !== (int) $job->id) {
            throw ValidationException::withMessages(['query' => 'Search query fixture belongs to another job.']);
        }

        return DB::transaction(function () use ($job, $normalized, $searchQuery, $candidateProductIds): ProspectingCandidate {
            $candidate = ProspectingCandidate::query()->firstOrCreate(
                [
                    'prospecting_search_job_id' => $job->id,
                    'fingerprint_hash' => $normalized['fingerprint_hash'],
                ],
                [
                    ...collect($normalized)->except(['sources', 'channels'])->all(),
                    'prospecting_search_query_id' => $searchQuery?->id,
                    'ai_agent_run_id' => null,
                    'status' => ProspectingCandidateStatus::PendingResolution,
                    'source_count' => 0,
                    'expires_at' => now()->addDays((int) config('ai-sales.prospecting.retention.unresolved_days', 30)),
                ],
            );
            if (! $candidate->wasRecentlyCreated && $candidate->status->terminal()) {
                return $candidate->fresh(['sources', 'channels', 'unitMatches', 'products.product']);
            }

            foreach ($normalized['sources'] as $sourceData) {
                $candidate->sources()->firstOrCreate(['evidence_hash' => $sourceData['evidence_hash']], $sourceData);
            }
            foreach ($normalized['channels'] as $channelData) {
                $candidate->channels()->firstOrCreate([
                    'channel_kind' => $channelData['channel_kind'],
                    'normalized_hash' => $channelData['normalized_hash'],
                ], $channelData);
            }
            foreach ($candidateProductIds as $productId) {
                $rationale = mb_substr(
                    $normalized['relevance_summary'] ?: 'Product scope inherited from the human-approved prospecting job.',
                    0,
                    1000,
                );
                $candidate->products()->firstOrCreate(['product_id' => $productId], [
                    'source' => CandidateProductSource::Job,
                    'status' => CandidateProductStatus::Approved,
                    'safe_rationale' => $rationale,
                    'evidence_reference' => 'prospecting-job:'.$job->public_id,
                    'evidence_hash' => hash('sha256', $job->schema_hash.'|product|'.$productId),
                    'confidence' => $normalized['confidence_components']['relevance'] ?? null,
                    'reviewed_by' => $job->approved_by,
                    'reviewed_at' => $job->approved_at,
                ]);
            }
            $candidate->update([
                'source_count' => $candidate->sources()->count(),
                'normalized_payload_hash' => $normalized['normalized_payload_hash'],
            ]);
            if ($searchQuery && $candidate->wasRecentlyCreated) {
                $searchQuery->increment('candidate_count');
            }

            return $candidate->fresh(['sources', 'channels', 'unitMatches', 'products.product']);
        }, 3);
    }
}
