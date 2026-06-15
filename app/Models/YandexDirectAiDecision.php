<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexDirectAiDecision extends Model
{
    use HasFactory;

    public const TYPE_LAUNCH = 'launch';
    public const TYPE_PAUSE = 'pause';
    public const TYPE_SCALE = 'scale';
    public const TYPE_OPTIMIZE = 'optimize';
    public const TYPE_IGNORE = 'ignore';
    public const TYPE_KEYWORD_EXPANSION = 'keyword_expansion';
    public const TYPE_NEGATIVE_KEYWORDS = 'negative_keywords';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'yandex_account_id',
        'good_id',
        'type',
        'confidence_score',
        'expected_impact',
        'risk_level',
        'status',
        'reason',
        'signals',
        'executed_at',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'expected_impact' => 'array',
        'reason' => 'array',
        'signals' => 'array',
        'executed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(YandexAccount::class, 'yandex_account_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_EXECUTED, self::STATUS_FAILED, self::STATUS_REJECTED], true);
    }
}
