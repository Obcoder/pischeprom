<?php

namespace App\Models;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitProductMatchOrigin;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitProductMatch extends Model
{
    protected $fillable = [
        'unit_id', 'unit_business_context_id', 'product_id', 'unit_source_id',
        'prospecting_candidate_product_id', 'match_type', 'status', 'origin',
        'evidence_confidence', 'safe_rationale', 'evidence_reference', 'evidence_hash',
        'rules_version', 'model_version', 'created_by', 'reviewed_by', 'reviewed_at', 'stale_after',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => UnitProductMatchType::class,
            'status' => UnitProductMatchStatus::class,
            'origin' => UnitProductMatchOrigin::class,
            'reviewed_at' => 'datetime',
            'stale_after' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $match): void {
            $context = UnitBusinessContext::query()->withoutGlobalScopes()->find($match->unit_business_context_id);
            if (! $context || (int) $context->unit_id !== (int) $match->unit_id) {
                throw new DomainException('Product match must belong to the selected Unit business context.');
            }

            $type = $match->match_type instanceof UnitProductMatchType
                ? $match->match_type
                : UnitProductMatchType::from((string) $match->match_type);
            if (($context->lane === BusinessLane::Sales && $type === UnitProductMatchType::PotentialOffer)
                || ($context->lane === BusinessLane::Procurement && $type === UnitProductMatchType::PotentialNeed)) {
                throw new DomainException('Product match direction conflicts with the Unit lane.');
            }
        });
        static::deleting(static function (): never {
            throw new DomainException('Unit Product matches use a review lifecycle and cannot be deleted.');
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(UnitSource::class, 'unit_source_id');
    }

    public function candidateProduct(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidateProduct::class, 'prospecting_candidate_product_id');
    }

    public function goodOfferFits(): HasMany
    {
        return $this->hasMany(UnitGoodMatch::class);
    }

    public function relevanceSnapshots(): HasMany
    {
        return $this->hasMany(UnitProductRelevanceSnapshot::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
