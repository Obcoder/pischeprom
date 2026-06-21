<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailingContact extends Model
{
    public const CONSENT_CONFIRMED = 'confirmed';

    protected $fillable = [
        'email', 'normalized_email', 'first_name', 'last_name', 'middle_name', 'company_name', 'position',
        'phone', 'website', 'source_type', 'source_url', 'contact_source', 'consent_status', 'consent_source',
        'consent_date', 'consent_proof_url', 'consent_proof_note', 'legal_basis_note', 'responsible_manager_id',
        'do_not_email', 'unsubscribed_at', 'complained_at', 'hard_bounced_at', 'soft_bounced_at',
        'soft_bounce_count', 'last_contacted_at', 'last_opened_at', 'last_clicked_at', 'tags', 'custom_fields',
    ];

    protected $casts = [
        'consent_date' => 'datetime',
        'do_not_email' => 'boolean',
        'unsubscribed_at' => 'datetime',
        'complained_at' => 'datetime',
        'hard_bounced_at' => 'datetime',
        'soft_bounced_at' => 'datetime',
        'soft_bounce_count' => 'integer',
        'last_contacted_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (MailingContact $contact): void {
            $contact->email = trim((string) $contact->email);
            $contact->normalized_email = self::normalizeEmail($contact->normalized_email ?: $contact->email);
        });
    }

    public static function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    public function sets(): BelongsToMany
    {
        return $this->belongsToMany(MailingContactSet::class, 'mailing_contact_set_members', 'contact_id', 'set_id')
            ->withPivot(['added_by', 'added_at', 'status']);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailingCampaignRecipient::class, 'contact_id');
    }

    public function scopeEligibleForMass(Builder $query): Builder
    {
        return $query
            ->where('consent_status', self::CONSENT_CONFIRMED)
            ->where('do_not_email', false)
            ->whereNull('unsubscribed_at')
            ->whereNull('complained_at')
            ->whereNull('hard_bounced_at')
            ->whereNotIn('normalized_email', MailingSuppression::query()->select('normalized_email'));
    }
}
