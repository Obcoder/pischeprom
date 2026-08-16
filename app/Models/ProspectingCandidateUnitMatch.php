<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectingCandidateUnitMatch extends Model
{
    protected $fillable = [
        'prospecting_candidate_id', 'unit_id', 'signal_code', 'strength', 'rank',
        'evidence_hash', 'evidence_reference', 'review_status',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
