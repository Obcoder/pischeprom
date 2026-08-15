<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiProcessingContour;
use Illuminate\Database\Eloquent\Model;

class AiProviderCapability extends Model
{
    protected $fillable = [
        'provider_code', 'provider_route', 'model_id', 'contour', 'capability', 'status',
        'max_context_tokens', 'max_output_tokens', 'evidence_reference', 'evidence_hash',
        'verified_by', 'verified_at', 'expires_at', 'probe_version',
    ];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'status' => AiCapabilityVerificationStatus::class,
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
