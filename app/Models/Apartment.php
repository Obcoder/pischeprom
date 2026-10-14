<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Apartment extends Model
{
    public const TYPES = ['apartment', 'office', 'premise'];

    protected $fillable = ['number', 'type', 'building_floor_id', 'notes'];

    protected $casts = ['building_id' => 'integer', 'building_floor_id' => 'integer'];

    protected $appends = ['label'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(BuildingFloor::class, 'building_floor_id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)->withTimestamps();
    }

    public function getLabelAttribute(): string
    {
        return match ($this->type) {
            'office' => 'офис ',
            'premise' => 'пом. ',
            default => 'кв. ',
        }.$this->number;
    }
}
