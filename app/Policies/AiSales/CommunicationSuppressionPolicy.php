<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Models\CommunicationSuppression;
use App\Models\User;

class CommunicationSuppressionPolicy
{
    public function __construct(private readonly OutreachAuthorizationService $authorization) {}

    public function update(User $user, CommunicationSuppression $suppression): bool
    {
        return $suppression->businessContext !== null
            && $this->authorization->can($user, OutreachAuthorizationService::MANAGE_SUPPRESSIONS, $suppression->businessContext);
    }
}
