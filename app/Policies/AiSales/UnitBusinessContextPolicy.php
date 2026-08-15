<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\UnitBusinessContext;
use App\Models\User;

class UnitBusinessContextPolicy
{
    public function __construct(private readonly UnitContextAuthorizationService $authorization) {}

    public function view(User $user, UnitBusinessContext $context): bool
    {
        return $this->authorization->canViewLane($user, $context->lane);
    }

    public function update(User $user, UnitBusinessContext $context): bool
    {
        return $this->view($user, $context)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_CONTEXTS);
    }

    public function proposeEntity(User $user, UnitBusinessContext $context): bool
    {
        return $this->view($user, $context)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::PROPOSE_ENTITY);
    }
}
