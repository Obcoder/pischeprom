<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'is_current' => $this->is_current,
            'status' => $this->status?->value,
            'routing_profile' => $this->routing_profile,
            'vehicle_profile_hash' => $this->vehicle_profile_hash,
            'request_hash' => $this->request_hash,
            'distance_m' => $this->distance_m,
            'duration_s' => $this->duration_s,
            'shape_polyline6' => $this->shape_polyline6,
            'legs' => $this->legs,
            'routing_options' => $this->routing_options,
            'provider' => $this->provider,
            'routing_engine_version' => $this->routing_engine_version,
            'osm_data_version' => $this->osm_data_version,
            'calculated_at' => $this->calculated_at?->toISOString(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
