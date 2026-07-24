<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodStockMovement extends Model
{
    use HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_WRITE_OFF = 'write_off';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'warehouse_id',
        'good_id',
        'measure_id',
        'type',
        'quantity_delta',
        'unit_price',
        'moved_at',
        'note',
    ];

    protected $casts = [
        'quantity_delta' => 'float',
        'unit_price' => 'float',
        'moved_at' => 'date',
    ];

    protected $appends = [
        'total_price',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) $this->quantity_delta * (float) $this->unit_price;
    }
}
