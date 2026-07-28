<?php

namespace App\Http\Requests\Logistics;

class UpdateLogisticsTripRequest extends StoreLogisticsTripRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip && (bool) $this->user()?->can('update', $trip);
    }
}
