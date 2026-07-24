<?php

namespace App\Policies;

use App\Models\BankConnection;
use App\Models\User;

class BankConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank.view');
    }

    public function view(User $user, BankConnection $connection): bool
    {
        return $user->can('bank.view');
    }

    public function sync(User $user, BankConnection $connection): bool
    {
        return $user->can('bank.sync');
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'crm')
            && $user->can('bank.manage_connection');
    }
}
