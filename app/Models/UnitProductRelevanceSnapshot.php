<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyScoreSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitProductRelevanceSnapshot extends Model
{
    use AppendOnlyScoreSnapshot;

    protected array $scoreSubjectFillable = ['unit_product_match_id', 'unit_id', 'unit_business_context_id'];

    protected function casts(): array
    {
        return $this->scoreSnapshotCasts();
    }

    public function productMatch(): BelongsTo
    {
        return $this->belongsTo(UnitProductMatch::class, 'unit_product_match_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function factors(): HasMany
    {
        return $this->hasMany(UnitProductRelevanceFactor::class);
    }

    public function baseSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_snapshot_id');
    }
}
