<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripStopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'region' => $this->city->region?->name,
            ]),
            'stop_type' => $this->stop_type?->value,
            'operation_type' => $this->operation_type?->value,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'planned_arrival_at' => $this->planned_arrival_at?->toISOString(),
            'planned_departure_at' => $this->planned_departure_at?->toISOString(),
            'actual_arrival_at' => $this->actual_arrival_at?->toISOString(),
            'actual_departure_at' => $this->actual_departure_at?->toISOString(),
            'cargo_weight_change_kg' => $this->cargo_weight_change_kg !== null ? (float) $this->cargo_weight_change_kg : null,
            'notes' => $this->notes,
        ];
    }
}
