<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apartment extends Model
{
    public const TYPES = ['apartment', 'office', 'premise'];

    protected $fillable = ['number', 'type'];

    protected $casts = ['building_id' => 'integer'];

    protected $appends = ['label'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
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
