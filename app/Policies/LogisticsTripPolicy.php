<?php

namespace App\Policies;

use App\Enums\Logistics\TripStatus;
use App\Models\LogisticsTrip;
use App\Models\User;
use App\Policies\Concerns\ChecksLogisticsPermissions;

class LogisticsTripPolicy
{
    use ChecksLogisticsPermissions;

    public function viewAny(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.view');
    }

    public function view(User $user, LogisticsTrip $trip): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasLogisticsPermission($user, 'logistics.trips.manage');
    }

    public function update(User $user, LogisticsTrip $trip): bool
    {
        if ($trip->status === TripStatus::Completed) {
            return $this->hasLogisticsPermission($user, 'logistics.technical.view')
                && $this->create($user);
        }

        return $this->create($user);
    }

    public function delete(User $user, LogisticsTrip $trip): bool
    {
        return $trip->status !== TripStatus::Completed && $this->create($user);
    }

    public function restore(User $user, LogisticsTrip $trip): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, LogisticsTrip $trip): bool
    {
        return false;
    }
}
