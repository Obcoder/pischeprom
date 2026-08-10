<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoListingGoodTransfer extends Model
{
    protected $fillable = [
        'avito_listing_good_link_id',
        'mode',
        'status',
        'selected_fields',
        'applied_fields',
        'manual_fields',
        'source_snapshot',
        'remote_meta',
    ];

    protected $hidden = [
        'source_snapshot',
        'remote_meta',
    ];

    protected function casts(): array
    {
        return [
            'selected_fields' => 'array',
            'applied_fields' => 'array',
            'manual_fields' => 'array',
            'source_snapshot' => 'encrypted:array',
            'remote_meta' => 'encrypted:array',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(AvitoListingGoodLink::class, 'avito_listing_good_link_id');
    }
}
