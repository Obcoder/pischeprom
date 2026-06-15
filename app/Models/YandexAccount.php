<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YandexAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'yandex_login',
        'direct_client_login',
        'metrica_counter_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
        'last_checked_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(YandexDirectCampaign::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(YandexDirectDailyStat::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(YandexSyncLog::class);
    }

    public function directLaunchSessions(): HasMany
    {
        return $this->hasMany(DirectLaunchSession::class);
    }

    public function directAiDecisions(): HasMany
    {
        return $this->hasMany(YandexDirectAiDecision::class);
    }
}
