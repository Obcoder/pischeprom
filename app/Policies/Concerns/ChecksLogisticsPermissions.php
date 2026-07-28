<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Throwable;

trait ChecksLogisticsPermissions
{
    private function hasLogisticsPermission(User $user, string $permission): bool
    {
        if (($user->status ?? 'active') === 'blocked') {
            return false;
        }

        try {
            return $user->hasRole('admin', 'crm') || $user->hasPermissionTo($permission, 'crm');
        } catch (Throwable) {
            return false;
        }
    }
}
