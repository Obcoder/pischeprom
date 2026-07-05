<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CheckCommodity extends Pivot
{
    protected $table = 'check_commodity';

    public $timestamps = true;

    protected $fillable = [
        'check_id',
        'commodity_id',
        'quantity',
        'measure_id',
        'expense_article_id',
        'price',
    ];

    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
        'total_price' => 'float',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function expenseArticle(): BelongsTo
    {
        return $this->belongsTo(ExpenseArticle::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
