<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CheckService extends Pivot
{
    protected $table = 'check_service';

    public $timestamps = true;

    public $incrementing = true;

    protected $fillable = [
        'check_id',
        'service_id',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
