<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CancelFindBuyersJob
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly ProspectingSearchJobService $jobs,
    ) {}

    public function handle(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->drafts();
        $this->authorization->authorizeManage($actor);
        if ($job->purpose !== ProspectingPurpose::BuyerDiscovery
            || $job->wizard_version !== (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')) {
            throw ValidationException::withMessages(['job' => 'This is not a Stage 11 Find Buyers job.']);
        }

        return $this->jobs->cancel($job, $actor);
    }
}
