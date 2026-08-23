<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Services\ExecuteProspectingSearchQuery;
use App\Domain\AiSales\Services\IngestProspectingSearchCandidate;
use App\Domain\AiSales\Web\SafePublicPageFetcher;
use App\Models\Entity;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchResult;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;
use Throwable;

final class FindBuyersCanaryRunner
{
    public function __construct(
        private readonly ExecuteProspectingSearchQuery $executor,
        private readonly SafePublicPageFetcher $fetcher,
        private readonly IngestProspectingSearchCandidate $candidateIngestor,
    ) {}

    /** @return array<string, mixed> */
    public function run(
        FindBuyersCanaryContext $context,
        bool $live,
        FindBuyersCanaryHttpGuard $httpGuard,
    ): array {
        $unitCountBefore = Unit::query()->count();
        $entityCountBefore = Entity::query()->without(['buildings', 'classification', 'country'])->count();
        $base = [
            'status' => $live ? 'running' : 'dry_run_ready',
            'scenario' => 'find_buyers_broccoli_spb_stage11b_v1',
            'job_public_id' => $context->job->public_id,
            'product' => $context->productName,
            'launch_source_type' => $context->launchSourceType,
            'originating_good_count' => $context->originatingGoodCount,
            'planned_query_count' => 1,
            'executed_query_count' => $live ? 1 : 0,
            'generated_query' => $context->query->safe_display_query,
            'template_code' => $context->query->template_code,
            'template_hash' => $context->query->template_hash,
            'product_scope_hash' => $context->query->product_scope_hash,
            'plan_hash' => $context->query->plan_hash,
            'normalized_results' => 0,
            'registrable_domains' => 0,
            'duplicate_results' => 0,
            'blocked_results' => 0,
            'result_set_hash' => null,
            'fetch_attempts' => [],
            'successful_pages' => 0,
            'candidate_count' => 0,
            'candidate_status' => null,
            'candidate_safe_code' => null,
            'score_counts' => [
                'product_relevance' => 0,
                'prospect_priority' => 0,
            ],
            'unit_changes' => 0,
            'entity_changes' => 0,
            'email_sent' => false,
            'provider_retries' => 0,
            'provider_failovers' => 0,
        ];

        if (! $live) {
            return $base;
        }

        $execution = $this->executor->handle($context->query, $context->operator);
        if ((int) $execution->request_count > FindBuyersCanaryJobGuard::MAX_YANDEX_REQUESTS
            || (int) $execution->result_count > FindBuyersCanaryJobGuard::MAX_RESULTS) {
            throw new SearchProviderException('canary_budget', 'stage11b_search_budget_exceeded');
        }

        $results = $execution->results()->orderBy('rank')->get();
        $eligible = $results
            ->filter(fn (ProspectingSearchResult $result): bool => $result->duplicate_of_id === null)
            ->unique('registrable_domain')
            ->take($context->caps['fetch_domains'])
            ->values();
        $hosts = $eligible->map(fn (ProspectingSearchResult $result): string => mb_strtolower(
            (string) parse_url($result->canonical_url, PHP_URL_HOST),
        ))->filter()->values()->all();
        $httpGuard->allowPublicHosts($hosts);

        $successfulFetch = null;
        $successfulResult = null;
        $fetchAttempts = [];
        foreach ($eligible->take($context->caps['fetch_attempts']) as $index => $result) {
            if (! $httpGuard->canAttemptPage()) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => 'budget_blocked',
                    'safe_code' => 'stage11b_remaining_http_budget_insufficient',
                ];
                break;
            }
            try {
                $successfulFetch = $this->fetcher->fetch($result, $context->operator);
                $successfulResult = $result;
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => 'fetched',
                    'safe_code' => null,
                ];
                break;
            } catch (PolicyViolation $exception) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => $this->fetchOutcome($exception->errorCode),
                    'safe_code' => $exception->errorCode,
                ];
            } catch (SearchProviderException $exception) {
                $fetchAttempts[] = [
                    'attempt' => $index + 1,
                    'status' => $this->fetchOutcome($exception->safeCode),
                    'safe_code' => $exception->safeCode,
                ];
            }
        }

        $candidate = null;
        $candidateSafeCode = null;
        if ($successfulFetch !== null && $successfulResult !== null) {
            try {
                $candidate = $this->candidateIngestor->handle($successfulResult, $context->operator);
            } catch (ValidationException) {
                $candidateSafeCode = 'stage11b_candidate_minimum_evidence_not_met';
            } catch (Throwable) {
                throw new SearchProviderException('internal', 'stage11b_candidate_ingestion_failed_safely');
            }
        }

        $candidateCount = ProspectingCandidate::query()
            ->where('prospecting_search_job_id', $context->job->id)->count();
        if ($candidateCount > FindBuyersCanaryJobGuard::MAX_CANDIDATES) {
            throw new SearchProviderException('canary_budget', 'stage11b_candidate_budget_exceeded');
        }
        $unitChanges = Unit::query()->count() - $unitCountBefore;
        $entityChanges = Entity::query()->without(['buildings', 'classification', 'country'])->count() - $entityCountBefore;
        if ($unitChanges !== 0 || $entityChanges !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_unit_entity_change_blocked');
        }

        $resultHashes = $results->pluck('result_hash')->sort()->values()->implode('|');

        return [
            ...$base,
            'status' => $results->isEmpty() ? 'no_results' : 'completed',
            'normalized_results' => $results->count(),
            'registrable_domains' => $results->pluck('registrable_domain')->filter()->unique()->count(),
            'duplicate_results' => (int) $execution->duplicate_count,
            'blocked_results' => (int) $execution->blocked_result_count,
            'result_set_hash' => $resultHashes !== '' ? hash('sha256', $resultHashes) : null,
            'safe_request_id_hash' => $execution->safe_request_id
                ? hash('sha256', (string) $execution->safe_request_id)
                : null,
            'fetch_attempts' => $fetchAttempts,
            'successful_pages' => $successfulFetch ? 1 : 0,
            'candidate_count' => $candidateCount,
            'candidate_status' => $candidate?->status?->value,
            'candidate_safe_code' => $candidateSafeCode,
            'unit_changes' => $unitChanges,
            'entity_changes' => $entityChanges,
        ];
    }

    private function fetchOutcome(string $safeCode): string
    {
        return match (true) {
            str_starts_with($safeCode, 'robots_') => 'robots_blocked',
            str_contains($safeCode, 'dns'), str_contains($safeCode, 'private'), str_contains($safeCode, 'address') => 'unsafe_address',
            str_contains($safeCode, 'content_type') => 'unsupported_content_type',
            str_contains($safeCode, 'timeout'), str_contains($safeCode, 'connection') => 'timeout',
            str_contains($safeCode, 'too_large') => 'too_large',
            str_contains($safeCode, 'redirect') => 'redirect_blocked',
            default => 'provider_error',
        };
    }
}
