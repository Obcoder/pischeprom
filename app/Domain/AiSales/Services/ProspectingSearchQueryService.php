<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingSearchQueryService
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly AiToolDlpGuard $dlp,
    ) {}

    public function recordFixture(
        ProspectingSearchJob $job,
        array $attributes,
        bool $repositoryOwnedSynthetic = false,
    ): ProspectingSearchQuery {
        $this->features->candidateImport();
        $this->features->assertNoLiveSearch();
        if (! app()->environment(['local', 'testing']) || ! $repositoryOwnedSynthetic) {
            throw ValidationException::withMessages(['query' => 'Only repository-owned local/testing query fixtures are accepted.']);
        }
        $display = mb_substr(trim((string) ($attributes['safe_display_query'] ?? '')), 0, 512);
        if ($display === '') {
            throw ValidationException::withMessages(['safe_display_query' => 'A bounded safe display query is required.']);
        }
        $this->dlp->assertPayloadSafe(['safe_display_query' => $display], AiProcessingContour::LocalRu, $job->lane);
        $hash = hash('sha256', mb_strtolower($display).'|'.($attributes['language'] ?? 'ru').'|'.($attributes['geography'] ?? ''));

        return DB::transaction(function () use ($job, $attributes, $display, $hash): ProspectingSearchQuery {
            $existing = $job->queries()->where('query_hash', $hash)->first();
            if ($existing) {
                return $existing;
            }
            if ($job->queries()->count() >= $job->max_queries) {
                throw ValidationException::withMessages(['query' => 'Job query ceiling reached.']);
            }

            return $job->queries()->create([
                'sequence' => $job->queries()->max('sequence') + 1,
                'query_hash' => $hash,
                'safe_display_query' => $display,
                'language' => mb_substr((string) ($attributes['language'] ?? 'ru'), 0, 12),
                'geography' => isset($attributes['geography']) ? mb_substr((string) $attributes['geography'], 0, 255) : null,
                'industry_intent' => isset($attributes['industry_intent']) ? mb_substr((string) $attributes['industry_intent'], 0, 255) : null,
                'status' => 'fixture_recorded',
                'result_count' => 0,
                'candidate_count' => 0,
                'search_provider_reference' => null,
                'executed_at' => null,
            ]);
        }, 3);
    }
}
