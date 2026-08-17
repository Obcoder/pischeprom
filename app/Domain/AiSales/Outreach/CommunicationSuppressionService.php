<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionScope;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\CommunicationSuppression;
use App\Models\CommunicationSuppressionDecision;
use App\Models\MailingContact;
use App\Models\MailingSuppression;
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
    ) {}

    public function create(User $actor, Unit $unit, UnitBusinessContext $context, array $data): CommunicationSuppression
    {
        $this->features->suppressionManagement();
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_SUPPRESSIONS, $unit, $context);
        $scope = CommunicationSuppressionScope::from($data['scope']);
        $endpointHash = null;
        $domainHash = null;

        if ($scope === CommunicationSuppressionScope::Endpoint) {
            $contact = UnitContactContextLink::query()->with('email')->findOrFail($data['unit_contact_context_link_id']);
            if ((int) $contact->unit_id !== (int) $unit->id || (int) $contact->unit_business_context_id !== (int) $context->id || ! $contact->email) {
                throw new PolicyViolation('suppression_contact_scope_mismatch', 'Suppression contact does not belong to the selected Unit context.');
            }
            $endpointHash = $this->permissions->endpointHash($contact->email->address);
        }
        if ($scope === CommunicationSuppressionScope::Domain) {
            $domain = Str::lower(trim((string) ($data['domain'] ?? '')));
            if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
                throw new PolicyViolation('suppression_domain_invalid', 'Suppression domain is invalid.');
            }
            $domainHash = hash('sha256', $domain);
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

        return DB::transaction(function () use ($payload, $actor): CommunicationSuppression {
            $suppression = CommunicationSuppression::query()->create($payload);
            $this->recordDecision($suppression, 'created', 'suppression_created', $actor);

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

    private function recordDecision(CommunicationSuppression $suppression, string $action, string $reason, User $actor, ?string $note = null): void
    {
        $sequence = $suppression->decisions()->count() + 1;
        CommunicationSuppressionDecision::query()->create([
            'communication_suppression_id' => $suppression->id, 'action' => $action,
            'reason_code' => $reason, 'safe_note' => $note,
            'decision_hash' => AiCanonicalJson::hash([
                'suppression_public_id' => $suppression->public_id, 'action' => $action,
                'reason' => $reason, 'note' => $note, 'actor_id' => $actor->id, 'sequence' => $sequence,
            ]),
            'decided_by' => $actor->id, 'decided_at' => now(),
        ]);
    }
}
