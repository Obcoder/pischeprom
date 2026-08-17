<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitFindBuyersPlanForReview
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
        private readonly ProspectingSearchJobService $jobs,
    ) {}

    public function handle(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->planning();
        $this->authorization->authorizePlan($actor);
        if ($job->purpose !== ProspectingPurpose::BuyerDiscovery
            || $job->wizard_version !== (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')
            || $job->status !== ProspectingJobStatus::Draft
            || (int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the owner may submit a current Find Buyers draft.']);
        }

        $plan = $this->planner->plan($job);
        $queries = $job->queries()->where('plan_hash', $plan->planHash)
            ->where('plan_status', 'review_required')->get();
        if ($queries->count() !== count($plan->items)
            || $queries->pluck('query_hash')->sort()->values()->all()
                !== collect($plan->items)->pluck('queryHash')->sort()->values()->all()) {
            throw ValidationException::withMessages(['plan' => 'A current code-owned query plan is required before review submission.']);
        }

        return DB::transaction(function () use ($job, $actor): ProspectingSearchJob {
            $submitted = $this->jobs->submit($job, $actor);
            $submitted->update(['submitted_by' => $actor->id, 'submitted_at' => now()]);

            return $submitted->fresh([
                'owner:id,name', 'reviewer:id,name', 'submitter:id,name',
                'products' => fn ($query) => $query->without(['category', 'manufacturers'])
                    ->select(['products.id', 'products.rus', 'products.eng']),
                'goods:id,name',
            ]);
        }, 3);
    }
}
