<?php

namespace App\Policies;

use App\Models\LogisticsCityDistance;
use App\Models\User;
use App\Policies\Concerns\ChecksLogisticsPermissions;

class LogisticsCityDistancePolicy
{
    use ChecksLogisticsPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.view');
    }

    public function view(User $user, LogisticsCityDistance $distance): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.matrix.manage');
    }

    public function update(User $user, LogisticsCityDistance $distance): bool
    {
        return $this->create($user);
    }
}
