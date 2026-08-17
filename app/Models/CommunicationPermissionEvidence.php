<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\CommunicationEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CommunicationPermissionEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'communication_permission_evidence';

    protected $fillable = [
        'communication_permission_id', 'evidence_type', 'safe_reference', 'content_hash', 'scope_hash',
        'captured_at', 'source_controller', 'safe_note', 'audit_hash', 'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['evidence_type' => CommunicationEvidenceType::class, 'captured_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Permission evidence is append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Permission evidence is append-only.');
        });
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(CommunicationPermission::class, 'communication_permission_id');
    }
}
