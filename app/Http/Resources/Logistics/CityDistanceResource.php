<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityDistanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_city_id' => $this->from_city_id,
            'to_city_id' => $this->to_city_id,
            'routing_profile' => $this->routing_profile,
            'vehicle_profile_hash' => $this->vehicle_profile_hash,
            'status' => $this->status?->value,
            'distance_m' => $this->distance_m,
            'duration_s' => $this->duration_s,
            'provider' => $this->provider,
            'routing_engine_version' => $this->routing_engine_version,
            'osm_data_version' => $this->osm_data_version,
            'request_hash' => $this->request_hash,
            'calculated_at' => $this->calculated_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'manual_note' => $this->manual_note,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
