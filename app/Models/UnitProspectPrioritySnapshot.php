<?php

namespace App\Models;

use App\Models\Concerns\AppendOnlyScoreSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitProspectPrioritySnapshot extends Model
{
    use AppendOnlyScoreSnapshot;

    protected array $scoreSubjectFillable = ['unit_business_context_id', 'unit_id'];

    protected function casts(): array
    {
        return $this->scoreSnapshotCasts();
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
        return $this->hasMany(UnitProspectPriorityFactor::class);
    }

    public function baseSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_snapshot_id');
    }
}
