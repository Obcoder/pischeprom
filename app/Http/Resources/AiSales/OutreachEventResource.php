<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutreachEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_event_id' => $this->provider_event_id,
            'type' => $this->normalized_event_type,
            'status' => $this->normalized_status,
            'event_time' => $this->event_time?->toISOString(),
            'verified_at' => $this->verified_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'safe_error_code' => $this->safe_error_code,
            'safe_summary' => $this->safe_summary,
        ];
    }
}
