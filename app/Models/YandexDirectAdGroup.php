<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YandexDirectAdGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_direct_campaign_id',
        'good_id',
        'external_ad_group_id',
        'name',
        'status',
        'region_ids',
        'minus_keywords',
        'settings',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'region_ids' => 'array',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(YandexDirectCampaign::class, 'yandex_direct_campaign_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(YandexDirectAd::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(YandexDirectKeyword::class);
    }
}
