<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YandexDirectGeoRegion extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_region_id',
        'parent_id',
        'name',
        'type',
        'parent_names',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'external_region_id' => 'integer',
        'parent_id' => 'integer',
        'parent_names' => 'array',
        'raw' => 'array',
        'synced_at' => 'datetime',
    ];
}
