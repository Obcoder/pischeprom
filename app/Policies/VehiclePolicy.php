<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Policies\Concerns\ChecksLogisticsPermissions;

class VehiclePolicy
{
    use ChecksLogisticsPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.vehicles.manage');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }
}
