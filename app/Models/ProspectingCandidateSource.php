<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectingCandidateSource extends Model
{
    protected $fillable = [
        'prospecting_candidate_id', 'source_type', 'canonical_url', 'source_reference', 'title',
        'source_domain', 'bounded_excerpt', 'evidence_hash', 'accessed_at', 'published_at',
        'data_classification', 'visibility_scope', 'confidence', 'source_quality',
    ];

    protected function casts(): array
    {
        return [
            'data_classification' => DataClassification::class,
            'visibility_scope' => UnitVisibilityScope::class,
            'accessed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(ProspectingCandidateChannel::class);
    }
}
