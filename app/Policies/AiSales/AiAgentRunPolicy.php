<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\AiControlPlaneAuthorizationService;
use App\Models\AiAgentRun;
use App\Models\User;

class AiAgentRunPolicy
{
    public function __construct(private readonly AiControlPlaneAuthorizationService $authorization) {}

    public function view(User $user, AiAgentRun $run): bool
    {
        return $this->authorization->canViewRun($user, $run);
    }

    public function cancel(User $user, AiAgentRun $run): bool
    {
        return $this->authorization->canCancelRun($user, $run);
    }
}
