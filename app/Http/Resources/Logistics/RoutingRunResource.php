<?php

namespace App\Http\Resources\Logistics;

use App\Enums\Logistics\RoutingRunStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_type' => $this->operation_type?->value,
            'status' => $this->status?->value,
            'routing_profile' => $this->routing_profile,
            'total_pairs' => $this->total_pairs,
            'completed_pairs' => $this->completed_pairs,
            'failed_pairs' => $this->failed_pairs,
            'progress_percent' => $this->total_pairs > 0
                ? round(($this->completed_pairs + $this->failed_pairs) / $this->total_pairs * 100, 1)
                : ($this->status === RoutingRunStatus::Completed ? 100 : 0),
            'parameters' => $this->parameters,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'last_error' => $this->last_error,
            'initiator' => $this->whenLoaded('initiator', fn () => $this->initiator ? [
                'id' => $this->initiator->id,
                'name' => $this->initiator->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
