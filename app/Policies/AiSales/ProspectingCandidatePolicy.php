<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\ProspectingCandidate;
use App\Models\User;

class ProspectingCandidatePolicy
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->contains(fn ($lane) => $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $lane));
    }

    public function view(User $user, ProspectingCandidate $candidate): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $candidate->lane);
    }

    public function review(User $user, ProspectingCandidate $candidate): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::REVIEW, $candidate->lane);
    }

    public function resolve(User $user, ProspectingCandidate $candidate): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::RESOLVE, $candidate->lane);
    }
}
