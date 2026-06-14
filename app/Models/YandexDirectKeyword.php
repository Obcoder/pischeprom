<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexDirectKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_direct_ad_group_id',
        'good_id',
        'external_keyword_id',
        'phrase',
        'bid',
        'context_bid',
        'status',
        'is_negative',
    ];

    protected $casts = [
        'bid' => 'decimal:2',
        'context_bid' => 'decimal:2',
        'is_negative' => 'boolean',
    ];

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(YandexDirectAdGroup::class, 'yandex_direct_ad_group_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
