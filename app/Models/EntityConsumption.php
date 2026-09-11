<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityConsumption extends Model
{
    public const STATUSES = [
        'potential' => 'Потенциальная',
        'confirmed' => 'Подтверждённая',
        'closed' => 'Закрытая',
    ];

    protected $fillable = [
        'product_id',
        'quantity',
        'measure_id',
        'status',
        'comment',
    ];

    protected $attributes = [
        'status' => 'potential',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
