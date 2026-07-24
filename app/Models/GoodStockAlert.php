<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodStockAlert extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_NOTIFIED = 'notified';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'good_id',
        'max_chat_id',
        'user_id',
        'start_token_hash',
        'status',
        'expires_at',
        'activated_at',
        'confirmation_sent_at',
        'notified_at',
        'cancelled_at',
        'last_attempt_at',
        'attempts',
        'provider_message_id',
        'error_message',
    ];

    protected $hidden = [
        'start_token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'notified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function maxChat(): BelongsTo
    {
        return $this->belongsTo(MaxChat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
