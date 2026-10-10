<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodInquiry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'float',
        'measure_id' => 'integer',
        'unit_weight_kg' => 'float',
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

    public function measurement(): array
    {
        return [
            'measure_id' => $this->measure_id,
            'unit_label' => $this->unit_label ?? 'упак.',
            'kilograms_per_unit' => $this->unit_label !== null ? $this->unit_weight_kg : $this->package_weight,
        ];
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

    public function deliveryApartmentLabel(): ?string
    {
        return filled($this->delivery_apartment_number)
            ? (new Apartment([
                'number' => $this->delivery_apartment_number,
                'type' => $this->delivery_apartment_type ?? 'apartment',
            ]))->label
            : null;
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
