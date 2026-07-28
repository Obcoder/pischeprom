<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsTripExpense extends Model
{
    use HasFactory;

    protected $table = 'logistics_trip_expenses';

    protected $fillable = [
        'trip_id',
        'check_id',
        'expense_category_id',
        'allocated_amount',
        'currency_code',
        'occurred_at',
        'quantity',
        'unit',
        'unit_price',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(LogisticsTrip::class, 'trip_id')->withTrashed();
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LogisticsExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
