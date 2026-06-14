<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectLaunchSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_BUILDING = 'building';
    public const STATUS_SENDING = 'sending';
    public const STATUS_DRY_RUN = 'dry_run';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'good_id',
        'yandex_account_id',
        'status',
        'step',
        'error_message',
        'payload',
        'external_ids',
        'rollback_payload',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'external_ids' => 'array',
        'rollback_payload' => 'array',
        'finished_at' => 'datetime',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(YandexAccount::class, 'yandex_account_id');
    }
}
