<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Jobs\AiSales\ExecuteProspectingSearchQueryJob;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExecuteProspectingSearchJob
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
    ) {}

    /** @return list<int> */
    public function handle(ProspectingSearchJob $job, User $actor): array
    {
        $this->features->searchExecution();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::EXECUTE_SEARCH, $job->lane);
        if ($job->status !== ProspectingJobStatus::Approved || $job->cancelled_at !== null) {
            throw ValidationException::withMessages(['job' => 'Search execution requires a current approved Job.']);
        }

        $queries = $job->queries()->where('plan_status', 'approved')->orderBy('sequence')->get();
        if ($queries->isEmpty() || $queries->pluck('plan_hash')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['plan' => 'Exactly one approved query plan is required.']);
        }

        foreach ($queries as $query) {
            ExecuteProspectingSearchQueryJob::dispatch($query->id, $actor->id);
        }

        return $queries->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
