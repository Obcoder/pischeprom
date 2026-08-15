<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiProviderCapabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider_code,
            'route' => $this->provider_route,
            'model' => $this->model_id,
            'contour' => $this->contour->value,
            'capability' => $this->capability,
            'status' => $this->status->value,
            'max_context_tokens' => $this->max_context_tokens,
            'max_output_tokens' => $this->max_output_tokens,
            'evidence_hash' => $this->evidence_hash,
            'verified_at' => $this->verified_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'probe_version' => $this->probe_version,
        ];
    }
}
