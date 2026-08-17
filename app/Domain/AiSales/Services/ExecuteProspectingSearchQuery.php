<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Domain\AiSales\Search\SearchProviderRequest;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\ProspectingSearchExecution;
use App\Models\ProspectingSearchQuery;
use App\Models\User;
use App\Services\Yandex\YandexSearchProfileRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExecuteProspectingSearchQuery
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
        private readonly SearchProviderRegistry $providers,
        private readonly IngestProspectingSearchResults $ingest,
    ) {}

    public function handle(ProspectingSearchQuery $query, User $actor): ProspectingSearchExecution
    {
        $this->features->searchExecution();
        $query->loadMissing('job');
        $job = $query->job;
        $this->authorization->authorize($actor, ProspectingAuthorizationService::EXECUTE_SEARCH, $job->lane);
        if ($job->status !== ProspectingJobStatus::Approved || $job->cancelled_at !== null
            || $query->plan_status !== 'approved' || $query->plan_approved_by === null) {
            throw ValidationException::withMessages(['query' => 'Execution requires an approved current query plan.']);
        }

        $plan = $this->planner->plan($job);
        $plannedItem = collect($plan->items)->first(fn ($item) => $item->queryHash === $query->query_hash);
        if (! $plannedItem
            || ! hash_equals($plan->planHash, (string) $query->plan_hash)
            || ! hash_equals($plan->productScopeHash, (string) $query->product_scope_hash)
            || ! hash_equals($plannedItem->templateHash, (string) $query->template_hash)) {
            throw ValidationException::withMessages(['query' => 'Product scope or code-owned query plan changed after review.']);
        }

        $providerCode = 'existing_yandex';
        $profileCode = YandexSearchProfileRegistry::PROSPECTING;
        $maxResults = min(
            (int) $job->max_results_per_query,
            (int) config('ai-sales.prospecting.limits.max_search_results_per_query', 50),
        );
        $requestHash = AiCanonicalJson::hash([
            'provider' => $providerCode,
            'profile' => $profileCode,
            'query_id' => $query->id,
            'query_hash' => $query->query_hash,
            'plan_hash' => $query->plan_hash,
            'max_results' => $maxResults,
        ]);
        $reservedRequestCount = max(1, (int) ceil($maxResults / 10));

        $execution = DB::transaction(function () use ($job, $query, $actor, $providerCode, $profileCode, $requestHash, $reservedRequestCount): ProspectingSearchExecution {
            $lockedJob = $job->newQuery()->lockForUpdate()->findOrFail($job->id);
            if ($lockedJob->status !== ProspectingJobStatus::Approved || $lockedJob->cancelled_at !== null) {
                throw ValidationException::withMessages(['job' => 'Search execution requires a current approved Job.']);
            }
            $existing = ProspectingSearchExecution::query()
                ->where('prospecting_search_query_id', $query->id)
                ->where('request_hash', $requestHash)
                ->first();
            if ($existing) {
                return $existing;
            }
            $requestCount = ProspectingSearchExecution::query()
                ->where('prospecting_search_job_id', $lockedJob->id)
                ->sum('request_count');
            if ($requestCount + $reservedRequestCount > (int) config('ai-sales.prospecting.limits.max_search_requests_per_job', 20)) {
                throw ValidationException::withMessages(['budget' => 'The code-owned search request budget is exhausted.']);
            }

            return ProspectingSearchExecution::query()->create([
                'prospecting_search_query_id' => $query->id,
                'request_hash' => $requestHash,
                'prospecting_search_job_id' => $job->id,
                'initiated_by' => $actor->id,
                'profile_code' => $profileCode,
                'provider_code' => $providerCode,
                'plan_hash' => $query->plan_hash,
                'status' => 'queued',
                'attempt' => 1,
                'request_count' => $reservedRequestCount,
            ]);
        }, 3);

        if ($execution->status === 'completed') {
            return $execution->fresh(['results', 'usage']);
        }
        if (! $execution->wasRecentlyCreated || $execution->attempt !== 1) {
            throw new SearchProviderException('idempotency', 'search_execution_replay_blocked');
        }

        $execution->update(['status' => 'processing', 'started_at' => now()]);
        $startedAt = hrtime(true);

        try {
            $provider = $this->providers->get($providerCode);
            if ($provider->code() !== $providerCode || ! in_array($profileCode, $provider->profiles(), true)) {
                throw new SearchProviderException('configuration', 'search_provider_profile_mismatch');
            }
            $response = $provider->search(new SearchProviderRequest(
                $job->public_id,
                $query->id,
                $profileCode,
                $query->safe_display_query,
                $query->language,
                $query->geography,
                $maxResults,
                $requestHash,
            ));
            if ($response->providerCode !== $providerCode || $response->profileCode !== $profileCode) {
                throw new SearchProviderException('protocol', 'search_provider_response_mismatch');
            }
            $durationMs = (int) ceil((hrtime(true) - $startedAt) / 1_000_000);
            $this->ingest->handle($execution, $response, $maxResults, $durationMs);

            return $execution->fresh(['results', 'usage']);
        } catch (SearchProviderException $exception) {
            $execution->update([
                'status' => 'failed',
                'error_category' => mb_substr($exception->category, 0, 64),
                'error_code' => mb_substr($exception->safeCode, 0, 96),
                'duration_ms' => (int) ceil((hrtime(true) - $startedAt) / 1_000_000),
                'completed_at' => now(),
            ]);
            throw $exception;
        } catch (Throwable) {
            $execution->update([
                'status' => 'failed',
                'error_category' => 'internal',
                'error_code' => 'search_execution_failed_safely',
                'duration_ms' => (int) ceil((hrtime(true) - $startedAt) / 1_000_000),
                'completed_at' => now(),
            ]);
            throw new SearchProviderException('internal', 'search_execution_failed_safely');
        }
    }
}
