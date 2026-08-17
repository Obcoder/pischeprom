<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PlanProspectingQueries
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
        private readonly ProspectingQueryPlanPersister $persister,
    ) {}

    public function handle(ProspectingSearchJob $job, User $actor): Collection
    {
        $this->features->queryPlanning();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::PLAN_SEARCH, $job->lane);
        if ($job->status !== ProspectingJobStatus::Approved) {
            throw ValidationException::withMessages(['job' => 'Query planning requires a human-approved Product-first Job.']);
        }

        $plan = $this->planner->plan($job);

        return $this->persister->persist($job, $plan);
    }
}
