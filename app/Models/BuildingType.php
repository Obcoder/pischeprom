<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}
