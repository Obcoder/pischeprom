<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\UnitObservation;
use App\Models\User;

class UnitObservationPolicy
{
    public function __construct(private readonly UnitContextAuthorizationService $authorization) {}

    public function view(User $user, UnitObservation $observation): bool
    {
        if ($observation->unit_business_context_id !== null) {
            $context = $observation->businessContext;

            if (! $context || ! $this->authorization->canViewLane($user, $context->lane)) {
                return false;
            }
        }

        return $this->authorization->canViewField(
            $user,
            $observation->visibility_scope,
            $observation->data_classification,
        );
    }

    public function verify(User $user, UnitObservation $observation): bool
    {
        return $this->view($user, $observation)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::VERIFY_OBSERVATIONS);
    }

    public function promote(User $user, UnitObservation $observation): bool
    {
        return $this->view($user, $observation)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::PROMOTE_OBSERVATIONS);
    }
}
