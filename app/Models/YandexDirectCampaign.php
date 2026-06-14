<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YandexDirectCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_account_id',
        'good_id',
        'external_campaign_id',
        'name',
        'type',
        'status',
        'daily_budget',
        'region_ids',
        'settings',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'region_ids' => 'array',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(YandexAccount::class, 'yandex_account_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function adGroups(): HasMany
    {
        return $this->hasMany(YandexDirectAdGroup::class);
    }
}
