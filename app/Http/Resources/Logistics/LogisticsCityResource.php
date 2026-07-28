<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogisticsCityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->relationLoaded('logisticsSetting') ? $this->logisticsSetting : null;

        return [
            'city_id' => $this->id,
            'name' => $this->name,
            'region' => $this->region?->name,
            'existing_latitude' => $this->latitude,
            'existing_longitude' => $this->longitude,
            'is_enabled' => $setting !== null,
            'routing_latitude' => $setting?->routing_latitude !== null ? (float) $setting->routing_latitude : null,
            'routing_longitude' => $setting?->routing_longitude !== null ? (float) $setting->routing_longitude : null,
            'coordinate_source' => $setting?->coordinate_source?->value,
            'source_reference' => $setting?->source_reference,
            'is_matrix_enabled' => (bool) $setting?->is_matrix_enabled,
            'coordinates_verified_at' => $setting?->coordinates_verified_at?->toISOString(),
            'coordinates_verified_by' => $setting?->coordinates_verified_by,
            'has_coordinates' => $setting?->hasRoutingPoint() ?? false,
            'is_verified' => $setting?->coordinates_verified_at !== null
                && $setting->hasRoutingPoint(),
        ];
    }
}
