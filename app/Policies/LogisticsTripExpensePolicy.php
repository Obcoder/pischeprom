<?php

namespace App\Policies;

use App\Models\LogisticsTripExpense;
use App\Models\User;
use App\Policies\Concerns\ChecksLogisticsPermissions;

class LogisticsTripExpensePolicy
{
    use ChecksLogisticsPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.view');
    }

    public function view(User $user, LogisticsTripExpense $expense): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.expenses.manage');
    }

    public function update(User $user, LogisticsTripExpense $expense): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, LogisticsTripExpense $expense): bool
    {
        return $this->create($user);
    }
}
