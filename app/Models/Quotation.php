<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'good_id',
        'unit_id',
        'price',
        'measure_id',
    ];
    protected $with = [
//        'good',
        'unit',
//        'measure',
    ];

//    public function good(): BelongsTo
//    {
//        return $this->belongsTo(Good::class);
//    }
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
    public function measures(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
