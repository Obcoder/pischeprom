<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\UnitGoodMatch;
use App\Models\User;

class UnitGoodMatchPolicy
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function view(User $user, UnitGoodMatch $match): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $match->businessContext->lane);
    }

    public function review(User $user, UnitGoodMatch $match): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::REVIEW_GOOD_MATCHES, $match->businessContext->lane);
    }
}
