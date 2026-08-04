<?php

namespace App\Models;

use App\Domain\AiPriceLists\Enums\VatMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierGoodPrice extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'vat_mode' => VatMode::class,
            'vat_rate' => 'decimal:4',
            'price_basis_quantity' => 'decimal:6',
            'minimum_order_quantity' => 'decimal:6',
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'provenance' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Supplier prices are append-only.'));
        static::deleting(static fn (): never => throw new LogicException('Supplier prices are append-only.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PriceListImport::class, 'price_list_import_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PriceListImportItem::class, 'price_list_import_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
