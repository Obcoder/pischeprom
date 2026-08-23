<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiResidencyVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider_code,
            'route' => $this->provider_route,
            'model' => $this->model_id,
            'declared_contour' => $this->declared_contour->value,
            'declared_country' => $this->declared_country,
            'evidence_reference' => $this->evidence_reference,
            'evidence_hash' => $this->evidence_hash,
            'status' => $this->status->value,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'probe_version' => $this->probe_version,
        ];
    }
}
