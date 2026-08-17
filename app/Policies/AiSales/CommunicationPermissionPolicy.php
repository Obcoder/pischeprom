<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Models\CommunicationPermission;
use App\Models\User;

class CommunicationPermissionPolicy
{
    public function __construct(private readonly OutreachAuthorizationService $authorization) {}

    public function view(User $user, CommunicationPermission $permission): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW_PERMISSIONS, $permission->businessContext);
    }

    public function update(User $user, CommunicationPermission $permission): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::MANAGE_PERMISSIONS, $permission->businessContext);
    }
}
