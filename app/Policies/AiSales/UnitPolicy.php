<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public const SEND_MAIL = 'mail.send';

    public function __construct(private readonly UnitContextAuthorizationService $authorization) {}

    public function view(User $user, Unit $unit): bool
    {
        return $this->authorization->hasPermission($user, UnitContextAuthorizationService::VIEW);
    }

    public function manageRoles(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_ROLES);
    }

    public function manageContexts(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_CONTEXTS);
    }

    public function manageAliases(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_ALIASES);
    }

    public function manageObservations(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_OBSERVATIONS);
    }

    public function manageContacts(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::MANAGE_CONTEXTS);
    }

    public function proposeEntity(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, UnitContextAuthorizationService::PROPOSE_ENTITY);
    }

    public function sendMail(User $user, Unit $unit): bool
    {
        return $this->view($user, $unit)
            && $this->authorization->hasPermission($user, self::SEND_MAIL);
    }
}
