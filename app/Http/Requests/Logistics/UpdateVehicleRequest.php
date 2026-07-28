<?php

namespace App\Http\Requests\Logistics;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        $vehicle = $this->route('vehicle');

        return $vehicle && (bool) $this->user()?->can('update', $vehicle);
    }
}
