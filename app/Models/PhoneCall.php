<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneCall extends Model
{
    use HasFactory;

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const DIRECTION_MISSED = 'missed';
    public const DIRECTION_UNKNOWN = 'unknown';

    protected $fillable = [
        'provider',
        'provider_call_id',
        'event_type',
        'direction',
        'status',
        'client_phone',
        'employee_user',
        'employee_extension',
        'employee_phone',
        'group_name',
        'diversion_phone',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'wait_seconds',
        'recording_url',
        'telephone_id',
        'entity_id',
        'unit_id',
        'lead_id',
        'raw_payload',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'wait_seconds' => 'integer',
        'raw_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function telephone(): BelongsTo
    {
        return $this->belongsTo(Telephone::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('client_phone', 'like', "%{$search}%")
                ->orWhere('employee_user', 'like', "%{$search}%")
                ->orWhere('employee_extension', 'like', "%{$search}%")
                ->orWhere('provider_call_id', 'like', "%{$search}%")
                ->orWhereHas('entity', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lead', fn (Builder $sq) => $sq->where('title', 'like', "%{$search}%"));
        });
    }
}
