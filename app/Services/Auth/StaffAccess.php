<?php

namespace App\Services\Auth;

use App\Models\User;
use Throwable;

class StaffAccess
{
    public function allows(?User $user): bool
    {
        if (! $user || $user->status !== 'active') {
            return false;
        }

        if ($user->type === 'employee') {
            return true;
        }

        // Existing CRM administrators can have the legacy customer account type.
        // A single operation permission does not make a customer a staff member.
        try {
            return $user->hasRole(['admin', 'manager'], 'crm');
        } catch (Throwable) {
            return false;
        }
    }
}
