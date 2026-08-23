<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CommunicationPermission extends Model
{
    protected $fillable = [
        'public_id', 'unit_id', 'unit_business_context_id', 'unit_contact_context_link_id', 'email_id',
        'channel', 'endpoint_hash', 'sender_scope', 'purpose', 'product_id', 'product_category_scope',
        'status', 'valid_from', 'valid_until', 'granted_at', 'revoked_at', 'policy_version', 'policy_hash',
        'evidence_set_hash', 'lock_version', 'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => MessagePurpose::class,
            'status' => CommunicationPermissionStatus::class,
            'valid_from' => 'datetime', 'valid_until' => 'datetime', 'granted_at' => 'datetime',
            'revoked_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Communication permissions use an audited lifecycle and cannot be deleted.');
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

    public function contactLink(): BelongsTo
    {
        return $this->belongsTo(UnitContactContextLink::class, 'unit_contact_context_link_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(CommunicationPermissionEvidence::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(CommunicationPermissionDecision::class);
    }
}
