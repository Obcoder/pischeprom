<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuthorizedMailDispatchAttempt extends Model
{
    protected $fillable = [
        'public_id', 'user_id', 'unit_id', 'route_name', 'idempotency_key_hash', 'request_hash',
        'recipient_count', 'attachment_count', 'status', 'safe_error_code', 'dispatched_at',
    ];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Authorized mail dispatch audit records cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
