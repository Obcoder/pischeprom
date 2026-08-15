<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiResidencyVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelResidencyVerification extends Model
{
    protected $fillable = [
        'provider_code', 'provider_route', 'model_id', 'declared_contour', 'declared_country',
        'evidence_reference', 'evidence_hash', 'verified_by', 'verified_at', 'expires_at',
        'status', 'probe_version', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'declared_contour' => AiProcessingContour::class,
            'status' => AiResidencyVerificationStatus::class,
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
