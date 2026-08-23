<?php

namespace App\Models;

use App\Domain\AiSales\Enums\CandidateProductSource;
use App\Domain\AiSales\Enums\CandidateProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectingCandidateProduct extends Model
{
    protected $fillable = [
        'prospecting_candidate_id', 'product_id', 'source', 'status', 'safe_rationale',
        'evidence_reference', 'evidence_hash', 'confidence', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => CandidateProductSource::class,
            'status' => CandidateProductStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitProductMatches(): HasMany
    {
        return $this->hasMany(UnitProductMatch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
