<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingFloor extends Model
{
    protected $fillable = ['number', 'name', 'notes'];

    protected $casts = ['building_id' => 'integer', 'number' => 'integer'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }
}
