<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    protected $with = [
        'city',
    ];

    protected $fillable = [
        'address',
        'city_id',
        'postcode',
    ]

    public function city()
    {
        return $this->belongsTo(City::class)
            ->withDefault();
    }
}
