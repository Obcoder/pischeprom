<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodStockAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'good_id',
        'is_in_stock',
        'became_available_at',
        'checked_at',
    ];

    protected $casts = [
        'is_in_stock' => 'boolean',
        'became_available_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
