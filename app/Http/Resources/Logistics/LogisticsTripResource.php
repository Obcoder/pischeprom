<?php

namespace App\Http\Resources\Logistics;

use App\Services\Logistics\TripMetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogisticsTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status?->value,
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle
                ? (new VehicleResource($this->vehicle))->resolve($request)
                : null),
            'carrier_entity_id' => $this->carrier_entity_id,
            'carrier' => $this->whenLoaded('carrier', fn () => $this->carrier ? [
                'id' => $this->carrier->id,
                'name' => $this->carrier->name,
            ] : null),
            'responsible_user_id' => $this->responsible_user_id,
            'responsible' => $this->whenLoaded('responsible', fn () => $this->responsible ? [
                'id' => $this->responsible->id,
                'name' => $this->responsible->name,
            ] : null),
            'planned_departure_at' => $this->planned_departure_at?->toISOString(),
            'planned_arrival_at' => $this->planned_arrival_at?->toISOString(),
            'actual_departure_at' => $this->actual_departure_at?->toISOString(),
            'actual_arrival_at' => $this->actual_arrival_at?->toISOString(),
            'cargo_description' => $this->cargo_description,
            'cargo_weight_kg' => $this->cargo_weight_kg !== null ? (float) $this->cargo_weight_kg : null,
            'cargo_volume_m3' => $this->cargo_volume_m3 !== null ? (float) $this->cargo_volume_m3 : null,
            'pallet_count' => $this->pallet_count,
            'temperature_mode' => $this->temperature_mode?->value,
            'temperature_min_c' => $this->temperature_min_c !== null ? (float) $this->temperature_min_c : null,
            'temperature_max_c' => $this->temperature_max_c !== null ? (float) $this->temperature_max_c : null,
            'planned_distance_m' => $this->planned_distance_m,
            'planned_duration_s' => $this->planned_duration_s,
            'actual_distance_m' => $this->actual_distance_m,
            'actual_distance_km' => $this->actual_distance_m !== null
                ? round($this->actual_distance_m / 1000, 3)
                : null,
            'actual_distance_source' => $this->actual_distance_source?->value,
            'odometer_start_km' => $this->odometer_start_km !== null ? (float) $this->odometer_start_km : null,
            'odometer_end_km' => $this->odometer_end_km !== null ? (float) $this->odometer_end_km : null,
            'routing_profile' => $this->routing_profile,
            'routing_profile_hash' => $this->routing_profile_hash,
            'route_calculated_at' => $this->route_calculated_at?->toISOString(),
            'notes' => $this->notes,
            'stops' => TripStopResource::collection($this->whenLoaded('stops')),
            'expenses' => TripExpenseResource::collection($this->whenLoaded('expenses')),
            'current_route' => $this->whenLoaded('currentRoute', fn () => $this->currentRoute
                ? ($request->boolean('summary')
                    ? (new TripRouteSummaryResource($this->currentRoute))->resolve($request)
                    : (new TripRouteResource($this->currentRoute))->resolve($request))
                : null),
            'map_url' => route('api.logistics.trips.map', ['trip' => $this->id], false),
            'stops_count' => $this->whenCounted('stops'),
            'expenses_count' => $this->whenCounted('expenses'),
            'route_summary' => $this->when(
                $this->relationLoaded('stops'),
                fn () => $this->routeSummary()
            ),
            'metrics' => $this->when(
                $this->relationLoaded('expenses'),
                fn () => app(TripMetricsService::class)->calculate($this->resource)
            ),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function routeSummary(): ?string
    {
        if ($this->stops->isEmpty()) {
            return null;
        }

        $names = $this->stops->map(fn ($stop) => $stop->city?->name)->filter()->values();

        return $names->join(' → ');
    }
}
