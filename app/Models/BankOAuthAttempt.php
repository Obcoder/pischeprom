<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankOAuthAttempt extends Model
{
    use HasUuids;

    protected $table = 'bank_oauth_attempts';

    protected $guarded = [];

    protected $hidden = [
        'state_hash',
        'nonce_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owner_entity_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
