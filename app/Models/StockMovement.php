<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_WRITE_OFF = 'write_off';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_CHECK_PURCHASE = 'check_purchase';

    public const SOURCE_CHECK_COMMODITY = 'check_commodity';

    protected $fillable = [
        'warehouse_id',
        'commodity_id',
        'measure_id',
        'type',
        'quantity_delta',
        'unit_price',
        'moved_at',
        'source_type',
        'source_id',
        'note',
    ];

    protected $casts = [
        'quantity_delta' => 'float',
        'unit_price' => 'float',
        'total_price' => 'float',
        'moved_at' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
