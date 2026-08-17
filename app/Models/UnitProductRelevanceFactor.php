<?php

namespace App\Models;

use App\Models\Concerns\ImmutableScoreFactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProductRelevanceFactor extends Model
{
    use ImmutableScoreFactor;

    protected array $scoreFactorSubjectFillable = ['unit_product_relevance_snapshot_id'];

    protected function casts(): array
    {
        return $this->scoreFactorCasts();
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(UnitProductRelevanceSnapshot::class, 'unit_product_relevance_snapshot_id');
    }
}
