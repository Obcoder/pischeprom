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

    protected $with = [
        'entity',
        'measure'
    ];

    public function entity(){
        return $this->belongsTo(Entity::class);
    }

    public function commodities()
    {
        return $this->belongsToMany(Commodity::class, 'check_commodity')
            ->using(check_commodity::class)
            ->withPivot('quantity', 'measure_id', 'price', 'total_price');
    }
    public function measure()
    {
        return $this->hasOneThrough(Measure::class, check_commodity::class, 'measure_id', 'id', 'id', 'measure_id')
            ->withDefault();
    }
}
