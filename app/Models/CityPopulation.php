<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityPopulation extends Model
{
    protected $fillable = [
        'city_id',
        'year',
        'population',
    ];

    protected $casts = [
        'city_id' => 'integer',
        'year' => 'integer',
        'population' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
