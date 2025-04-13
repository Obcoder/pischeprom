<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'wiki',
        'population',
        'region_id',
        'yandexmapsgeo',
        'twogis',
        'latitude',
        'longitude',
    ];

    protected $with = [
        'region',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)
            ->withDefault();
    }

    public function buildings()
    {
        return $this->hasMany(Building::class, 'city_id', 'id');
    }
}
