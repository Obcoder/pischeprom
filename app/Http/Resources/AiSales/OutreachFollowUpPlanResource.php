<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutreachFollowUpPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'dispatch_id' => $this->outreach_dispatch_id,
            'status' => $this->status?->value,
            'max_follow_ups' => $this->max_follow_ups,
            'earliest_at' => $this->earliest_at?->toISOString(),
            'recommendation_code' => $this->recommendation_code,
            'cancellation_reason' => $this->cancellation_reason,
            'auto_schedule' => false,
            'auto_send' => false,
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'position' => $step->position,
                'status' => $step->status?->value,
                'required_reviews' => $step->required_reviews,
            ])),
        ];
    }
}
