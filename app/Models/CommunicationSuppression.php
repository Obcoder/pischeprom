<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CommunicationSuppression extends Model
{
    protected $fillable = [
        'public_id', 'scope', 'channel', 'endpoint_hash', 'domain_hash', 'unit_id', 'unit_business_context_id',
        'reason', 'source', 'safe_evidence_reference', 'evidence_hash', 'active_from', 'active_until',
        'cleared_at', 'clear_reason_code', 'created_by', 'reviewed_by', 'cleared_by', 'audit_hash',
    ];

    protected function casts(): array
    {
        return [
            'scope' => CommunicationSuppressionScope::class, 'reason' => CommunicationSuppressionReason::class,
            'active_from' => 'datetime', 'active_until' => 'datetime', 'cleared_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Suppressions are cleared through audited decisions, not deleted.');
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

    public function decisions(): HasMany
    {
        return $this->hasMany(CommunicationSuppressionDecision::class);
    }
}
