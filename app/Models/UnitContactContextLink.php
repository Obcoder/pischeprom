<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\ProspectingCommunicationState;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitContactContextLink extends Model
{
    protected $fillable = [
        'unit_id',
        'unit_business_context_id',
        'unit_source_id',
        'channel_type',
        'email_id',
        'telephone_id',
        'uri_id',
        'channel_value_snapshot',
        'normalized_hash',
        'contact_role',
        'verification_status',
        'confidence',
        'data_classification',
        'visibility_scope',
        'communication_state',
        'review_required',
        'first_seen_at',
        'last_seen_at',
        'last_verified_at',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => ObservationVerificationStatus::class,
            'data_classification' => DataClassification::class,
            'visibility_scope' => UnitVisibilityScope::class,
            'confidence' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'review_required' => 'boolean',
            'communication_state' => ProspectingCommunicationState::class,
            'reviewed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $link): void {
            $references = collect([$link->email_id, $link->telephone_id, $link->uri_id])->filter();

            if ($references->count() !== 1) {
                throw new DomainException('A Unit contact context link must reference exactly one existing channel.');
            }

            $expectedType = $link->email_id ? 'email' : ($link->telephone_id ? 'telephone' : 'uri');

            if ($link->channel_type !== $expectedType) {
                throw new DomainException('Contact channel type does not match the referenced channel.');
            }
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(UnitSource::class, 'unit_source_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function telephone(): BelongsTo
    {
        return $this->belongsTo(Telephone::class);
    }

    public function uri(): BelongsTo
    {
        return $this->belongsTo(Uri::class);
    }

    public function communicationPermissions(): HasMany
    {
        return $this->hasMany(CommunicationPermission::class);
    }

    public function outreachDrafts(): HasMany
    {
        return $this->hasMany(OutreachDraft::class);
    }

    public function outreachDispatches(): HasMany
    {
        return $this->hasMany(OutreachDispatch::class);
    }
}
