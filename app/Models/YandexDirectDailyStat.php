<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexDirectDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_account_id',
        'good_id',
        'campaign_id',
        'ad_group_id',
        'ad_id',
        'keyword_id',
        'date',
        'impressions',
        'clicks',
        'cost',
        'avg_cpc',
        'ctr',
        'conversions',
        'cost_per_conversion',
        'raw',
    ];

    protected $casts = [
        'date' => 'date',
        'cost' => 'decimal:4',
        'avg_cpc' => 'decimal:4',
        'ctr' => 'decimal:4',
        'cost_per_conversion' => 'decimal:4',
        'raw' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(YandexAccount::class, 'yandex_account_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(YandexDirectCampaign::class, 'campaign_id');
    }

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(YandexDirectAdGroup::class, 'ad_group_id');
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(YandexDirectAd::class, 'ad_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(YandexDirectKeyword::class, 'keyword_id');
    }
}
