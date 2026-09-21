<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodInquiry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'integer',
        'package_weight' => 'float',
        'listed_price' => 'float',
        'proposed_price' => 'float',
        'consent_at' => 'datetime',
        'email_notified_at' => 'datetime',
        'max_delivered_to' => 'array',
        'next_notification_at' => 'datetime',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'order' => 'Заказ товара',
            'bargain' => 'Торг: предложение покупателя',
            default => 'Заявка на товар',
        };
    }

    public function scenarioLabel(): string
    {
        return match ($this->bargain_scenario) {
            'volume' => 'Готов взять больше за лучшую цену',
            'repeat' => 'Планирую регулярные закупки',
            'ready' => 'Готов к покупке после согласования',
            default => 'Индивидуальные условия',
        };
    }
}
