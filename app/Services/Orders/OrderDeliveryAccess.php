<?php

namespace App\Services\Orders;

use App\Models\User;

class OrderDeliveryAccess
{
    public static function allowed(?User $user, string $permission = 'orders.edit'): bool
    {
        if (! $user || $user->status !== 'active' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole('admin', 'crm') || ($user->type === 'employee'
            && ($user->checkPermissionTo($permission, 'crm') || $user->checkPermissionTo('warehouse.move', 'crm')));
    }
}
