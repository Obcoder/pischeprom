<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Search\SearchProviderResponse;
use App\Domain\AiSales\Web\SearchResultDeduplicator;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchResult;
use App\Models\ProspectingSearchUsageRecord;
use Illuminate\Support\Facades\DB;

class IngestProspectingSearchResults
{
    public function __construct(private readonly SearchResultDeduplicator $deduplicator) {}

    /** @return array{result_count: int, duplicate_count: int, blocked_count: int} */
    public function handle(
        ProspectingSearchExecution $execution,
        SearchProviderResponse $response,
        int $maxResults,
        int $durationMs,
    ): array {
        $normalized = $this->deduplicator->normalize($response->results, $maxResults);

        return DB::transaction(function () use ($execution, $response, $normalized, $durationMs): array {
            $duplicateCount = 0;
            $stored = 0;
            $localByHash = [];

            foreach ($normalized['accepted'] as $item) {
                $duplicate = null;
                if ($item['duplicate_of_result_hash']) {
                    $duplicate = $localByHash[$item['duplicate_of_result_hash']] ?? null;
                }
                $duplicate ??= ProspectingSearchResult::query()
                    ->where('prospecting_search_job_id', $execution->prospecting_search_job_id)
                    ->where('url_hash', $item['url_hash'])
                    ->oldest('id')
                    ->first();
                if ($duplicate) {
                    $duplicateCount++;
                }

                $result = ProspectingSearchResult::query()->firstOrCreate([
                    'prospecting_search_execution_id' => $execution->id,
                    'result_hash' => $item['result_hash'],
                ], [
                    'prospecting_search_job_id' => $execution->prospecting_search_job_id,
                    'prospecting_search_query_id' => $execution->prospecting_search_query_id,
                    'rank' => $item['rank'],
                    'result_type' => $item['result_type'],
                    'title' => $item['title'],
                    'snippet' => $item['snippet'],
                    'url' => $item['url'],
                    'canonical_url' => $item['canonical_url'],
                    'url_hash' => $item['url_hash'],
                    'registrable_domain' => $item['registrable_domain'],
                    'domain_hash' => $item['domain_hash'],
                    'duplicate_of_id' => $duplicate?->id,
                    'prospecting_candidate_id' => null,
                    'trust_level' => 'untrusted',
                    'instruction_authority' => 'none',
                    'fetch_status' => 'not_requested',
                    'research_status' => 'not_requested',
                ]);
                $localByHash[$item['result_hash']] = $result;
                $stored += $result->wasRecentlyCreated ? 1 : 0;
            }

            ProspectingSearchUsageRecord::query()->firstOrCreate([
                'prospecting_search_execution_id' => $execution->id,
            ], [
                'provider_code' => $response->providerCode,
                'profile_code' => $response->profileCode,
                'request_count' => $response->usage->requestCount,
                'result_count' => count($normalized['accepted']),
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'estimated_cost_rub' => $response->usage->estimatedCostRub,
                'safe_request_id' => $response->safeRequestId,
                'recorded_at' => now(),
            ]);

            $resultCount = ProspectingSearchResult::query()
                ->where('prospecting_search_execution_id', $execution->id)->count();
            $execution->update([
                'status' => 'completed',
                'request_count' => $response->usage->requestCount,
                'result_count' => $resultCount,
                'duplicate_count' => $duplicateCount,
                'blocked_result_count' => $normalized['blocked_count'],
                'duration_ms' => $durationMs,
                'safe_request_id' => $response->safeRequestId,
                'completed_at' => now(),
            ]);
            $execution->searchQuery()->update([
                'status' => 'completed',
                'result_count' => $resultCount,
                'updated_at' => now(),
            ]);

            return [
                'result_count' => $resultCount,
                'duplicate_count' => $duplicateCount,
                'blocked_count' => $normalized['blocked_count'],
            ];
        }, 3);
    }
}
