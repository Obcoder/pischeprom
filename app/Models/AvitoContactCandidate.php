<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoContactCandidate extends Model
{
    public const TYPE_PHONE = 'phone';

    public const TYPE_ADDRESS = 'address';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'avito_message_id',
        'type',
        'raw_value',
        'normalized_value',
        'fingerprint',
        'confidence',
        'status',
        'telephone_id',
        'building_id',
        'resolved_by_user_id',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'resolved_at' => 'datetime',
            'metadata' => 'encrypted:array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AvitoMessage::class, 'avito_message_id');
    }

    public function telephone(): BelongsTo
    {
        return $this->belongsTo(Telephone::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
