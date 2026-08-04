<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItemCandidate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'score_components' => 'array',
            'is_selected' => 'boolean',
            'is_rejected' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PriceListImportItem::class, 'price_list_import_item_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
