<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\UnitProductMatch;
use App\Models\User;

class UnitProductMatchPolicy
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function view(User $user, UnitProductMatch $match): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::VIEW, $match->businessContext->lane);
    }

    public function review(User $user, UnitProductMatch $match): bool
    {
        return $this->authorization->can($user, ProspectingAuthorizationService::REVIEW_PRODUCT_MATCHES, $match->businessContext->lane);
    }
}
