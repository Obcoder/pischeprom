<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CommunicationPermissionDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'communication_permission_id', 'from_status', 'to_status', 'reason_code', 'safe_note',
        'evidence_set_hash', 'decision_hash', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Permission decisions are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Permission decisions are append-only.');
        });
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(CommunicationPermission::class, 'communication_permission_id');
    }
}
