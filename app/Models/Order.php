<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number',
        'user_id',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_phone_source',
        'customer_account_type',
        'customer_city_name',
        'customer_entity_name',
        'delivery_address',
        'preferred_delivery_time',
        'total_amount',
        'total_weight',
        'currency_code',
        'submitted_at',
        'notified_at',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'total_weight' => 'float',
        'submitted_at' => 'datetime',
        'notified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
