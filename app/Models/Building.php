<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'address',
        'postcode',
    ];

    protected $casts = [
        'city_id' => 'integer',
    ];

    protected $with = [
        'city',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class)
            ->withDefault();
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
}
