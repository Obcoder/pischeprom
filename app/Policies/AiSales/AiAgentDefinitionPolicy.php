<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Models\User;

class AiAgentDefinitionPolicy
{
    public function __construct(private readonly AiControlPlaneAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->canView($user);
    }
}
