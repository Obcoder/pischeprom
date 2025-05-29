<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'good_id',
        'price',
        'currency_id',
    ];

    protected $with = [
        'currency',
        'good',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
