<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiProviderModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider_code,
            'route' => $this->provider_route,
            'model' => $this->model_id,
            'display_label' => $this->display_label,
            'endpoint_profile' => $this->endpoint_profile->value,
            'active_in_inventory' => $this->active_in_inventory,
            'first_seen_at' => $this->first_seen_at?->toISOString(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'safe_metadata' => $this->safe_metadata ?? [],
            'source_reference' => $this->source_reference,
            'metadata_hash' => $this->metadata_hash,
        ];
    }
}
