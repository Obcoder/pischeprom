<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\ProspectingSearchJob;
use App\Models\User;

class ProspectingSearchJobPolicy
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->contains(fn ($lane) => $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $lane));
    }

    public function create(User $user): bool
    {
        return collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->contains(fn ($lane) => $this->authorization->can($user, ProspectingAuthorizationService::MANAGE_JOBS, $lane));
    }

    public function view(User $user, ProspectingSearchJob $job): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $job->lane);
    }

    public function update(User $user, ProspectingSearchJob $job): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::MANAGE_JOBS, $job->lane);
    }

    public function review(User $user, ProspectingSearchJob $job): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::REVIEW, $job->lane);
    }
}
