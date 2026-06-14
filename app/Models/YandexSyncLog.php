<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'yandex_account_id',
        'entity_type',
        'entity_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(YandexAccount::class, 'yandex_account_id');
    }
}
