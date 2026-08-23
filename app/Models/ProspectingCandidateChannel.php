<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\ProspectingChannelKind;
use App\Domain\AiSales\Enums\ProspectingCommunicationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectingCandidateChannel extends Model
{
    protected $fillable = [
        'prospecting_candidate_id', 'prospecting_candidate_source_id', 'channel_kind',
        'normalized_hash', 'protected_value', 'masked_display', 'contact_role',
        'verification_status', 'confidence', 'data_classification', 'communication_state',
        'last_verified_at',
    ];

    protected $hidden = ['protected_value'];

    protected function casts(): array
    {
        return [
            'channel_kind' => ProspectingChannelKind::class,
            'protected_value' => 'encrypted',
            'verification_status' => ObservationVerificationStatus::class,
            'data_classification' => DataClassification::class,
            'communication_state' => ProspectingCommunicationState::class,
            'last_verified_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidateSource::class, 'prospecting_candidate_source_id');
    }
}
