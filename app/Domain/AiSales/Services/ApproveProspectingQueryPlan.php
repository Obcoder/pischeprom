<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveProspectingQueryPlan
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
    ) {}

    public function handle(ProspectingSearchJob $job, User $actor): Collection
    {
        $this->features->queryPlanning();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW_SEARCH, $job->lane);
        if ($job->status !== ProspectingJobStatus::Approved) {
            throw ValidationException::withMessages(['job' => 'Only an approved Product-first Job can approve a query plan.']);
        }

        $plan = $this->planner->plan($job);
        $queries = $job->queries()->where('plan_hash', $plan->planHash)->orderBy('sequence')->get();
        $plannedQueryHashes = collect($plan->items)->pluck('queryHash')->unique()->sort()->values();
        if ($queries->count() !== $plannedQueryHashes->count()
            || $queries->pluck('query_hash')->unique()->sort()->values()->all() !== $plannedQueryHashes->all()
            || $queries->contains(fn ($query) => ! hash_equals((string) $query->product_scope_hash, $plan->productScopeHash))) {
            throw ValidationException::withMessages(['plan' => 'The persisted query plan is missing or stale.']);
        }

        DB::transaction(function () use ($queries, $actor): void {
            foreach ($queries as $query) {
                if ($query->plan_status === 'approved') {
                    continue;
                }
                $query->update([
                    'plan_status' => 'approved',
                    'status' => 'approved',
                    'plan_approved_by' => $actor->id,
                    'plan_approved_at' => now(),
                ]);
            }
        }, 3);

        return $job->queries()->where('plan_hash', $plan->planHash)->orderBy('sequence')->get();
    }
}
