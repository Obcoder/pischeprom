<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Consumption extends Pivot
{
    use HasFactory;

    protected $with = [
        'measure',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)
            ->withDefault();
    }
    public function measure(){
        return $this->hasOne(Measure::class, 'measure_id', 'id')
            ->withDefault();
    }
}
