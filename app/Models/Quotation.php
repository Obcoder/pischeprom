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
        'good',
        'unit',
        'measure',
    ];

    public function good()
    {
        return $this->HasOne(Good::class);
    }
    public function unit()
    {
        return $this->HasOne(Unit::class);
    }
    public function measures()
    {
        return $this->HasOne(Measure::class);
    }
}
