<?php

namespace App\Models;

use App\Models\Concerns\ImmutableScoreFactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitGoodFitFactor extends Model
{
    use ImmutableScoreFactor;

    protected array $scoreFactorSubjectFillable = ['unit_good_fit_snapshot_id'];

    protected function casts(): array
    {
        return $this->scoreFactorCasts();
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(UnitGoodFitSnapshot::class, 'unit_good_fit_snapshot_id');
    }
}
