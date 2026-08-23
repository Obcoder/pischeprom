<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CommunicationSuppressionDecision extends Model
{
    public $timestamps = false;

    protected $fillable = ['communication_suppression_id', 'action', 'reason_code', 'safe_note', 'decision_hash', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Suppression decisions are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Suppression decisions are append-only.');
        });
    }

    public function suppression(): BelongsTo
    {
        return $this->belongsTo(CommunicationSuppression::class, 'communication_suppression_id');
    }
}
