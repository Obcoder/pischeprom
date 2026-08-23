<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingCandidateSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->source_type,
            'url' => $this->canonical_url,
            'reference' => $this->source_reference,
            'title' => $this->title,
            'domain' => $this->source_domain,
            'excerpt' => $this->bounded_excerpt,
            'evidence_hash' => $this->evidence_hash,
            'confidence' => $this->confidence,
            'source_quality' => $this->source_quality,
            'accessed_at' => $this->accessed_at?->toISOString(),
        ];
    }
}
