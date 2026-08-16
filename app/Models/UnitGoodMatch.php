<?php

namespace App\Models;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitGoodMatchOrigin;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitGoodMatchType;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitGoodMatch extends Model
{
    protected $fillable = [
        'unit_id', 'unit_business_context_id', 'good_id', 'unit_source_id',
        'prospecting_candidate_id', 'match_type', 'relevance', 'confidence', 'safe_rationale',
        'evidence_reference', 'evidence_hash', 'status', 'origin', 'rules_version',
        'model_version', 'created_by', 'reviewed_by', 'reviewed_at', 'stale_after',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => UnitGoodMatchType::class,
            'status' => UnitGoodMatchStatus::class,
            'origin' => UnitGoodMatchOrigin::class,
            'reviewed_at' => 'datetime',
            'stale_after' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $match): void {
            $context = UnitBusinessContext::query()->withoutGlobalScopes()->find($match->unit_business_context_id);
            if (! $context || (int) $context->unit_id !== (int) $match->unit_id) {
                throw new DomainException('Good match must belong to the selected Unit business context.');
            }

            $type = $match->match_type instanceof UnitGoodMatchType
                ? $match->match_type
                : UnitGoodMatchType::from((string) $match->match_type);
            if (($context->lane === BusinessLane::Sales && $type === UnitGoodMatchType::PotentialOffer)
                || ($context->lane === BusinessLane::Procurement && $type === UnitGoodMatchType::PotentialNeed)) {
                throw new DomainException('Good match direction conflicts with the Unit lane.');
            }
        });
        static::deleting(static function (): never {
            throw new DomainException('Unit Good matches use a review lifecycle and cannot be deleted.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(UnitSource::class, 'unit_source_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
