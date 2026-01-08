<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $casts = [
        'price' => 'decimal:2',
    ];
    protected $fillable = [
        'good_id',
        'unit_id',
        'price',
        'measure_id',
    ];
    protected $with = [
        'good',
        'unit',
        'measure',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
