<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoListingGoodLink extends Model
{
    protected $fillable = [
        'avito_account_id',
        'avito_item_id',
        'good_id',
        'last_price_value_id',
        'last_selected_fields',
        'last_media_ids',
        'include_facts',
        'last_prepared_at',
        'last_applied_at',
    ];

    protected function casts(): array
    {
        return [
            'avito_account_id' => 'integer',
            'avito_item_id' => 'integer',
            'good_id' => 'integer',
            'last_price_value_id' => 'integer',
            'last_selected_fields' => 'array',
            'last_media_ids' => 'array',
            'include_facts' => 'boolean',
            'last_prepared_at' => 'datetime',
            'last_applied_at' => 'datetime',
        ];
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AvitoListingGoodTransfer::class);
    }
}
