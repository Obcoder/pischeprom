<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    protected $with = [
        'entity',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function commodities()
    {
        return $this->belongsToMany(Commodity::class, 'check_commodity')
            ->using(CheckCommodity::class)
            ->withPivot('quantity', 'measure_id', 'price', 'total_price')
            ->withTimestamps();
    }
}
