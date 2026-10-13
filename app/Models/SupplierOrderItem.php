<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'float', 'unit_price' => 'float', 'kilograms_per_unit' => 'float',
        'source_order_item_ids' => 'array', 'quotation_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            $orderId = $item->getOriginal('supplier_order_id') ?: $item->supplier_order_id;
            abort_unless(SupplierOrder::whereKey($orderId)->value('status') === 'draft', 409,
                'Состав отправленного заказа поставщику изменять нельзя.');
            if ($item->exists && $item->isDirty('supplier_order_id')) {
                abort(409, 'Позицию нельзя переносить в другой заказ.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function measurement(): array
    {
        return ['measure_id' => $this->measure_id, 'unit_label' => $this->unit_label,
            'kilograms_per_unit' => $this->kilograms_per_unit];
    }
}
