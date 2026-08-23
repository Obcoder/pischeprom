<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Domain\AiSales\Services\ProspectingQueryPlanPersister;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class BuildFindBuyersQueryPlan
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
        private readonly ProspectingQueryPlanPersister $persister,
    ) {}

    public function handle(ProspectingSearchJob $job, User $actor): Collection
    {
        $this->features->planning();
        $this->authorization->authorizePlan($actor);
        if ($job->purpose !== ProspectingPurpose::BuyerDiscovery
            || $job->wizard_version !== (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')
            || $job->status !== ProspectingJobStatus::Draft
            || (int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the owner may plan a current Find Buyers draft.']);
        }

        return $this->persister->persist($job, $this->planner->plan($job));
    }
}
