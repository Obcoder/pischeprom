<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'vin' => $this->vin,
            'vehicle_type' => $this->vehicle_type?->value,
            'owner_entity_id' => $this->owner_entity_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'status' => $this->status?->value,
            'payload_capacity_kg' => $this->payload_capacity_kg !== null ? (float) $this->payload_capacity_kg : null,
            'cargo_volume_m3' => $this->cargo_volume_m3 !== null ? (float) $this->cargo_volume_m3 : null,
            'curb_weight_kg' => $this->curb_weight_kg !== null ? (float) $this->curb_weight_kg : null,
            'gross_weight_kg' => $this->gross_weight_kg !== null ? (float) $this->gross_weight_kg : null,
            'length_m' => $this->length_m !== null ? (float) $this->length_m : null,
            'width_m' => $this->width_m !== null ? (float) $this->width_m : null,
            'height_m' => $this->height_m !== null ? (float) $this->height_m : null,
            'axle_count' => $this->axle_count,
            'max_axle_load_t' => $this->max_axle_load_t !== null ? (float) $this->max_axle_load_t : null,
            'fuel_type' => $this->fuel_type,
            'fuel_tank_capacity_l' => $this->fuel_tank_capacity_l !== null ? (float) $this->fuel_tank_capacity_l : null,
            'average_fuel_consumption_l_per_100km' => $this->average_fuel_consumption_l_per_100km !== null
                ? (float) $this->average_fuel_consumption_l_per_100km
                : null,
            'is_active' => $this->is_active,
            'is_available_for_planning' => $this->is_active && $this->status?->value === 'active',
            'notes' => $this->notes,
            'trips_count' => $this->whenCounted('trips'),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
