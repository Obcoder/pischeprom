<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Consumption extends Pivot
{
    use HasFactory;

    protected $table = 'consumptions';
    protected $fillable = ['product_id', 'unit_id', 'quantity', 'measure_id'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class)
            ->withDefault();
    }
    public function measure()
    {
        return $this->belongsTo(Measure::class, 'measure_id', 'id')
            ->withDefault();
    }
}
