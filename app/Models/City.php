<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $with = [
        'region',
    ];

    public function region(){
        return $this->belongsTo(Region::class)
            ->withDefault();
    }

    public function buildings()
    {
        return $this->hasMany(Building::class, 'city_id', 'id');
    }
}
