<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class UnitDossierAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'unit_name_snapshot',
        'unit_business_context_id',
        'event_type',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_user_id',
        'summary',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Unit dossier audit events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Unit dossier audit events are append-only.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
