<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'area',
        'yandex_direct_region_ids',
        'use_for_yandex_direct',
    ];

    protected $casts = [
        'area' => 'float',
        'yandex_direct_region_ids' => 'array',
        'use_for_yandex_direct' => 'boolean',
    ];

    protected $with = [
        'country',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
