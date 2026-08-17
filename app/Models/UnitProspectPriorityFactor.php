<?php

namespace App\Models;

use App\Models\Concerns\ImmutableScoreFactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProspectPriorityFactor extends Model
{
    use ImmutableScoreFactor;

    protected array $scoreFactorSubjectFillable = ['unit_prospect_priority_snapshot_id'];

    protected function casts(): array
    {
        return $this->scoreFactorCasts();
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(UnitProspectPrioritySnapshot::class, 'unit_prospect_priority_snapshot_id');
    }
}
