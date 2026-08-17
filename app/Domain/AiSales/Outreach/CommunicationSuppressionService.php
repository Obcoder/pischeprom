<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionScope;
use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\CommunicationSuppression;
use App\Models\CommunicationSuppressionDecision;
use App\Models\MailingContact;
use App\Models\MailingSuppression;
use App\Models\OutreachDispatch;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommunicationSuppressionService
{
    public function __construct(
        private readonly OutreachAuthorizationService $authorization,
        private readonly OutreachFeatureGuard $features,
        private readonly CommunicationPermissionService $permissions,
        private readonly OutreachDispatchStateMachine $states,
        private readonly OutreachFollowUpCancellationService $followUps,
    ) {}

    public function create(User $actor, Unit $unit, UnitBusinessContext $context, array $data): CommunicationSuppression
    {
        $this->features->suppressionManagement();
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_SUPPRESSIONS, $unit, $context);
        $scope = CommunicationSuppressionScope::from($data['scope']);
        $endpointHash = null;
        $domainHash = null;
        $normalizedDomain = null;

        if ($scope === CommunicationSuppressionScope::Endpoint) {
            $contact = UnitContactContextLink::query()->with('email')->findOrFail($data['unit_contact_context_link_id']);
            if ((int) $contact->unit_id !== (int) $unit->id || (int) $contact->unit_business_context_id !== (int) $context->id || ! $contact->email) {
                throw new PolicyViolation('suppression_contact_scope_mismatch', 'Suppression contact does not belong to the selected Unit context.');
            }
            $endpointHash = $this->permissions->endpointHash($contact->email->address);
        }
        if ($scope === CommunicationSuppressionScope::Domain) {
            $normalizedDomain = Str::lower(trim((string) ($data['domain'] ?? '')));
            if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $normalizedDomain)) {
                throw new PolicyViolation('suppression_domain_invalid', 'Suppression domain is invalid.');
            }
            $domainHash = hash('sha256', $normalizedDomain);
        }

        $payload = [
            'public_id' => (string) Str::uuid(), 'scope' => $scope->value, 'channel' => 'email',
            'endpoint_hash' => $endpointHash, 'domain_hash' => $domainHash,
            'unit_id' => in_array($scope, [CommunicationSuppressionScope::Unit, CommunicationSuppressionScope::Context, CommunicationSuppressionScope::Endpoint], true) ? $unit->id : null,
            'unit_business_context_id' => in_array($scope, [CommunicationSuppressionScope::Context, CommunicationSuppressionScope::Endpoint], true) ? $context->id : null,
            'reason' => $data['reason'], 'source' => $data['source'],
            'safe_evidence_reference' => $data['evidence_reference'] ?? null,
            'evidence_hash' => $data['evidence_hash'], 'active_from' => $data['active_from'] ?? now(),
            'active_until' => $data['active_until'] ?? null, 'created_by' => $actor->id, 'reviewed_by' => $actor->id,
        ];
        $payload['audit_hash'] = AiCanonicalJson::hash([
            'public_id' => $payload['public_id'], 'scope' => $payload['scope'], 'endpoint_hash' => $endpointHash,
            'domain_hash' => $domainHash, 'unit_id' => $payload['unit_id'], 'context_id' => $payload['unit_business_context_id'],
            'reason' => $payload['reason'], 'source' => $payload['source'], 'evidence_hash' => $payload['evidence_hash'],
        ]);

        return DB::transaction(function () use ($payload, $actor, $unit, $context, $data, $normalizedDomain): CommunicationSuppression {
            Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            UnitBusinessContext::query()->whereKey($context->id)->lockForUpdate()->firstOrFail();
            if (! empty($data['unit_contact_context_link_id'])) {
                UnitContactContextLink::query()->whereKey($data['unit_contact_context_link_id'])->lockForUpdate()->firstOrFail();
            }
            $suppression = CommunicationSuppression::query()->create($payload);
            $this->recordDecision($suppression, 'created', 'suppression_created', $actor);
            $this->blockMatchingDispatches(
                $suppression,
                isset($data['unit_contact_context_link_id']) ? (int) $data['unit_contact_context_link_id'] : null,
                $normalizedDomain,
            );

            return $suppression;
        });
    }

    public function clear(CommunicationSuppression $suppression, User $actor, string $reasonCode, ?string $safeNote): CommunicationSuppression
    {
        $this->features->suppressionManagement();
        $suppression->loadMissing(['unit', 'businessContext']);
        if (! $suppression->unit || ! $suppression->businessContext) {
            throw new PolicyViolation('suppression_clear_global_forbidden', 'Global and domain suppressions require a separate governance process.');
        }
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_SUPPRESSIONS, $suppression->unit, $suppression->businessContext);
        if ($suppression->cleared_at) {
            throw new PolicyViolation('suppression_already_cleared', 'Suppression is already cleared.');
        }
        if (in_array($suppression->reason, [
            CommunicationSuppressionReason::Unsubscribed,
            CommunicationSuppressionReason::Complaint,
            CommunicationSuppressionReason::HardBounce,
            CommunicationSuppressionReason::LegalHold,
        ], true)) {
            throw new PolicyViolation(
                'suppression_clear_governance_required',
                'This suppression reason requires a separate governance process.',
            );
        }

        return DB::transaction(function () use ($suppression, $actor, $reasonCode, $safeNote): CommunicationSuppression {
            $suppression->forceFill(['cleared_at' => now(), 'cleared_by' => $actor->id, 'clear_reason_code' => $reasonCode])->save();
            $this->recordDecision($suppression, 'cleared', $reasonCode, $actor, $safeNote);

            return $suppression->fresh();
        });
    }

    /** @return list<string> */
    public function blockReasons(Unit $unit, UnitBusinessContext $context, UnitContactContextLink $contact): array
    {
        $contact->loadMissing('email');
        if (! $contact->email) {
            return ['recipient_email_missing'];
        }
        $address = Str::lower(trim($contact->email->address));
        $parts = explode('@', $address, 2);
        $endpointHash = $this->permissions->endpointHash($address);
        $domainHash = isset($parts[1]) ? hash('sha256', $parts[1]) : null;
        $now = now();
        $reasons = [];

        $active = CommunicationSuppression::query()
            ->where('channel', 'email')->whereNull('cleared_at')->where('active_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('active_until')->orWhere('active_until', '>', $now))
            ->where(function ($q) use ($unit, $context, $endpointHash, $domainHash): void {
                $q->where('scope', CommunicationSuppressionScope::Global->value)
                    ->orWhere(fn ($nested) => $nested->where('scope', CommunicationSuppressionScope::Unit->value)->where('unit_id', $unit->id))
                    ->orWhere(fn ($nested) => $nested->where('scope', CommunicationSuppressionScope::Context->value)->where('unit_business_context_id', $context->id))
                    ->orWhere(fn ($nested) => $nested->where('scope', CommunicationSuppressionScope::Endpoint->value)->where('endpoint_hash', $endpointHash));
                if ($domainHash) {
                    $q->orWhere(fn ($nested) => $nested->where('scope', CommunicationSuppressionScope::Domain->value)->where('domain_hash', $domainHash));
                }
            })->pluck('reason')->all();
        foreach ($active as $reason) {
            $reasons[] = 'stage12_suppression_'.($reason instanceof \BackedEnum ? $reason->value : $reason);
        }

        $state = $contact->communication_state?->value ?? (string) $contact->communication_state;
        if (in_array($state, ['do_not_contact', 'suppressed'], true)) {
            $reasons[] = 'contact_state_'.$state;
        }

        if (Schema::hasTable('mailing_suppression_list')
            && MailingSuppression::query()->where('normalized_email', $address)->exists()) {
            $reasons[] = 'legacy_mailing_suppression';
        }
        if (Schema::hasTable('mailing_contacts')) {
            $legacy = MailingContact::query()->where('normalized_email', $address)->first();
            if ($legacy?->do_not_email) {
                $reasons[] = 'legacy_do_not_email';
            }
            if ($legacy?->unsubscribed_at) {
                $reasons[] = 'legacy_unsubscribed';
            }
            if ($legacy?->complained_at) {
                $reasons[] = 'legacy_complaint';
            }
            if ($legacy?->hard_bounced_at) {
                $reasons[] = 'legacy_hard_bounce';
            }
            if (in_array($legacy?->consent_status, ['revoked', 'rejected'], true)) {
                $reasons[] = 'legacy_consent_revoked';
            }
        }

        return array_values(array_unique($reasons));
    }

    public function createSystemEndpointSuppression(
        OutreachDispatch $dispatch,
        CommunicationSuppressionReason $reason,
        string $source,
        string $safeReference,
    ): CommunicationSuppression {
        $dispatch->loadMissing(['unit', 'businessContext', 'contactLink.email']);
        if (! $dispatch->contactLink?->email) {
            throw new PolicyViolation('suppression_contact_missing', 'Outreach contact is unavailable for suppression.');
        }

        $endpointHash = $this->permissions->endpointHash($dispatch->contactLink->email->address);

        return DB::transaction(function () use ($dispatch, $reason, $source, $safeReference, $endpointHash): CommunicationSuppression {
            Unit::query()->whereKey($dispatch->unit_id)->lockForUpdate()->firstOrFail();
            UnitBusinessContext::query()->whereKey($dispatch->unit_business_context_id)->lockForUpdate()->firstOrFail();
            UnitContactContextLink::query()->whereKey($dispatch->unit_contact_context_link_id)->lockForUpdate()->firstOrFail();
            $existing = CommunicationSuppression::query()
                ->where('scope', CommunicationSuppressionScope::Endpoint->value)
                ->where('channel', 'email')
                ->where('endpoint_hash', $endpointHash)
                ->where('unit_business_context_id', $dispatch->unit_business_context_id)
                ->where('reason', $reason->value)
                ->whereNull('cleared_at')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->blockMatchingDispatches($existing, $dispatch->unit_contact_context_link_id, null);

                return $existing;
            }

            $publicId = (string) Str::uuid();
            $evidenceHash = AiCanonicalJson::hash([
                'dispatch_public_id' => $dispatch->public_id,
                'reason' => $reason->value,
                'source' => $source,
                'safe_reference' => $safeReference,
            ]);
            $suppression = CommunicationSuppression::query()->create([
                'public_id' => $publicId,
                'scope' => CommunicationSuppressionScope::Endpoint,
                'channel' => 'email',
                'endpoint_hash' => $endpointHash,
                'unit_id' => $dispatch->unit_id,
                'unit_business_context_id' => $dispatch->unit_business_context_id,
                'reason' => $reason,
                'source' => mb_substr($source, 0, 64),
                'safe_evidence_reference' => mb_substr($safeReference, 0, 512),
                'evidence_hash' => $evidenceHash,
                'active_from' => now(),
                'audit_hash' => AiCanonicalJson::hash([
                    'public_id' => $publicId,
                    'scope' => CommunicationSuppressionScope::Endpoint->value,
                    'endpoint_hash' => $endpointHash,
                    'context_id' => $dispatch->unit_business_context_id,
                    'reason' => $reason->value,
                    'source' => $source,
                    'evidence_hash' => $evidenceHash,
                ]),
            ]);
            $this->recordDecision($suppression, 'created', 'system_verified_'.$reason->value, null);
            $this->blockMatchingDispatches($suppression, $dispatch->unit_contact_context_link_id, null);

            return $suppression;
        });
    }

    private function recordDecision(CommunicationSuppression $suppression, string $action, string $reason, ?User $actor, ?string $note = null): void
    {
        $sequence = $suppression->decisions()->count() + 1;
        CommunicationSuppressionDecision::query()->create([
            'communication_suppression_id' => $suppression->id, 'action' => $action,
            'reason_code' => $reason, 'safe_note' => $note,
            'decision_hash' => AiCanonicalJson::hash([
                'suppression_public_id' => $suppression->public_id, 'action' => $action,
                'reason' => $reason, 'note' => $note, 'actor_id' => $actor?->id, 'sequence' => $sequence,
            ]),
            'decided_by' => $actor?->id, 'decided_at' => now(),
        ]);
    }

    private function blockMatchingDispatches(
        CommunicationSuppression $suppression,
        ?int $contactLinkId,
        ?string $domain,
    ): void {
        $query = OutreachDispatch::query();
        $endpointEmailId = $suppression->scope === CommunicationSuppressionScope::Endpoint && $contactLinkId
            ? UnitContactContextLink::query()->whereKey($contactLinkId)->value('email_id')
            : null;
        match ($suppression->scope) {
            CommunicationSuppressionScope::Global => null,
            CommunicationSuppressionScope::Unit => $query->where('unit_id', $suppression->unit_id),
            CommunicationSuppressionScope::Context => $query->where('unit_business_context_id', $suppression->unit_business_context_id),
            CommunicationSuppressionScope::Endpoint => $query->where(function ($endpointQuery) use ($suppression, $contactLinkId, $endpointEmailId): void {
                $endpointQuery->where('unit_contact_context_link_id', $contactLinkId ?: 0)
                    ->orWhereHas(
                        'contactLink',
                        fn ($contactQuery) => $contactQuery
                            ->where('normalized_hash', $suppression->endpoint_hash)
                            ->when($endpointEmailId, fn ($emailQuery) => $emailQuery->orWhere('email_id', $endpointEmailId)),
                    );
            }),
            CommunicationSuppressionScope::Domain => $query->whereHas(
                'contactLink.email',
                fn ($emailQuery) => $emailQuery->where('address', 'like', '%@'.($domain ?: '__invalid__')),
            ),
        };

        foreach ($query->lockForUpdate()->get() as $dispatch) {
            if (in_array($dispatch->state, [
                OutreachDispatchState::Prepared,
                OutreachDispatchState::ReviewRequired,
                OutreachDispatchState::Ready,
                OutreachDispatchState::QueuePending,
                OutreachDispatchState::Queued,
            ], true)) {
                $this->states->transition($dispatch, OutreachDispatchState::Blocked, 'communication_suppression_created');
            }
            $followUpStatus = $suppression->reason === CommunicationSuppressionReason::HardBounce
                ? OutreachFollowUpStatus::CancelledBounce
                : OutreachFollowUpStatus::CancelledSuppression;
            $this->followUps->cancel(
                $dispatch,
                $followUpStatus,
                'communication_suppression_'.$suppression->reason->value,
            );
        }
    }
}
