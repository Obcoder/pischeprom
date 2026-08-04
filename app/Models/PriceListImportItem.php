<?php

namespace App\Models;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\VatMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PriceListImportItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_cells' => 'array',
            'field_evidence' => 'array',
            'warnings' => 'array',
            'user_corrections' => 'array',
            'decision_status' => ItemDecisionStatus::class,
            'match_class' => MatchClass::class,
            'vat_mode' => VatMode::class,
            'units_per_package' => 'decimal:6',
            'net_quantity' => 'decimal:6',
            'price_basis_quantity' => 'decimal:6',
            'minimum_order_quantity' => 'decimal:6',
            'price' => 'decimal:6',
            'vat_rate' => 'decimal:4',
            'match_score' => 'decimal:4',
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PriceListImport::class, 'price_list_import_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(PriceListItemCandidate::class)->orderBy('rank');
    }

    public function supplierPrice(): HasOne
    {
        return $this->hasOne(SupplierGoodPrice::class);
    }
}
