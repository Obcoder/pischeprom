<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\CommunicationPermission;
use App\Models\CommunicationPermissionDecision;
use App\Models\CommunicationPermissionEvidence;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunicationPermissionService
{
    public function __construct(
        private readonly OutreachAuthorizationService $authorization,
        private readonly OutreachFeatureGuard $features,
    ) {}

    public function create(
        User $actor,
        Unit $unit,
        UnitBusinessContext $context,
        UnitContactContextLink $contact,
        array $data,
    ): CommunicationPermission {
        $this->features->permissionLedger();
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_PERMISSIONS, $unit, $context);
        $this->assertContact($unit, $context, $contact);

        $purpose = MessagePurpose::from($data['purpose']);
        if ($purpose !== MessagePurpose::AdvertisingOutreach) {
            throw new PolicyViolation('outreach_purpose_not_supported', 'Stage 12 ledger accepts advertising outreach only.');
        }
        if (! $contact->email_id || ! $contact->email) {
            throw new PolicyViolation('permission_email_required', 'Communication permission requires an existing Email contact link.');
        }

        $policyVersion = (string) config('ai-sales.outreach.policy_version', 'stage12-v1');
        $policyHash = AiCanonicalJson::hash(['version' => $policyVersion, 'purpose' => $purpose->value, 'precedence' => 'suppression_wins']);
        $scope = [
            'unit_id' => $unit->id, 'context_id' => $context->id, 'contact_id' => $contact->id,
            'email_id' => $contact->email_id, 'sender_scope' => (string) config('ai-sales.outreach.sender_scope'),
            'purpose' => $purpose->value, 'product_id' => $data['product_id'] ?? null,
            'category' => $data['product_category_scope'] ?? null,
        ];
        $scopeHash = AiCanonicalJson::hash($scope);

        return DB::transaction(function () use ($actor, $unit, $context, $contact, $data, $purpose, $policyVersion, $policyHash, $scopeHash): CommunicationPermission {
            $permission = CommunicationPermission::query()->create([
                'public_id' => (string) Str::uuid(),
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'unit_contact_context_link_id' => $contact->id,
                'email_id' => $contact->email_id,
                'channel' => 'email',
                'endpoint_hash' => $this->endpointHash($contact->email->address),
                'sender_scope' => (string) config('ai-sales.outreach.sender_scope'),
                'purpose' => $purpose,
                'product_id' => $data['product_id'] ?? null,
                'product_category_scope' => $data['product_category_scope'] ?? null,
                'status' => CommunicationPermissionStatus::PendingReview,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'policy_version' => $policyVersion,
                'policy_hash' => $policyHash,
                'created_by' => $actor->id,
            ]);
            $evidenceHashes = [];
            foreach ($data['evidence'] as $index => $evidence) {
                $auditHash = AiCanonicalJson::hash([
                    'permission_public_id' => $permission->public_id, 'index' => $index,
                    'type' => $evidence['type'], 'reference' => $evidence['reference'],
                    'content_hash' => $evidence['content_hash'], 'scope_hash' => $scopeHash,
                ]);
                CommunicationPermissionEvidence::query()->create([
                    'communication_permission_id' => $permission->id,
                    'evidence_type' => $evidence['type'], 'safe_reference' => $evidence['reference'],
                    'content_hash' => $evidence['content_hash'], 'scope_hash' => $scopeHash,
                    'captured_at' => $evidence['captured_at'], 'source_controller' => $evidence['source_controller'] ?? null,
                    'safe_note' => $evidence['safe_note'] ?? null, 'audit_hash' => $auditHash,
                    'created_by' => $actor->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                ]);
                $evidenceHashes[] = $auditHash;
            }
            $permission->forceFill(['evidence_set_hash' => AiCanonicalJson::hash(['evidence' => $evidenceHashes])])->save();
            $this->recordDecision($permission, null, CommunicationPermissionStatus::PendingReview, 'created_for_review', $actor);

            return $permission->fresh(['evidence', 'contactLink.email']);
        });
    }

    public function review(CommunicationPermission $permission, User $actor, CommunicationPermissionStatus $decision, string $reasonCode, ?string $safeNote): CommunicationPermission
    {
        $this->features->permissionLedger();
        $permission->loadMissing(['unit', 'businessContext', 'evidence']);
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_PERMISSIONS, $permission->unit, $permission->businessContext);
        if (! in_array($decision, [CommunicationPermissionStatus::Granted, CommunicationPermissionStatus::Rejected], true)) {
            throw new PolicyViolation('permission_review_transition_invalid', 'Permission review decision is invalid.');
        }
        if ($permission->status !== CommunicationPermissionStatus::PendingReview || $permission->evidence->isEmpty()) {
            throw new PolicyViolation('permission_evidence_required', 'Reviewed evidence is required before permission can be granted.');
        }
        if ($decision === CommunicationPermissionStatus::Granted && $permission->valid_until && $permission->valid_until->isPast()) {
            throw new PolicyViolation('permission_expired', 'Expired permission cannot be granted.');
        }

        return DB::transaction(function () use ($permission, $actor, $decision, $reasonCode, $safeNote): CommunicationPermission {
            $from = $permission->status;
            $permission->forceFill([
                'status' => $decision,
                'granted_at' => $decision === CommunicationPermissionStatus::Granted ? now() : null,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'lock_version' => $permission->lock_version + 1,
            ])->save();
            $this->recordDecision($permission, $from, $decision, $reasonCode, $actor, $safeNote);

            return $permission->fresh(['evidence']);
        });
    }

    public function revoke(CommunicationPermission $permission, User $actor, string $reasonCode, ?string $safeNote): CommunicationPermission
    {
        $this->features->permissionLedger();
        $permission->loadMissing(['unit', 'businessContext']);
        $this->authorization->authorize($actor, OutreachAuthorizationService::MANAGE_PERMISSIONS, $permission->unit, $permission->businessContext);
        if ($permission->status !== CommunicationPermissionStatus::Granted) {
            throw new PolicyViolation('permission_revoke_transition_invalid', 'Only granted permission can be revoked.');
        }

        return DB::transaction(function () use ($permission, $actor, $reasonCode, $safeNote): CommunicationPermission {
            $from = $permission->status;
            $permission->forceFill([
                'status' => CommunicationPermissionStatus::Revoked, 'revoked_at' => now(),
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'lock_version' => $permission->lock_version + 1,
            ])->save();
            $this->recordDecision($permission, $from, CommunicationPermissionStatus::Revoked, $reasonCode, $actor, $safeNote);

            return $permission->fresh();
        });
    }

    public function activePermissionFor(int $contextId, int $contactId, int $productId, MessagePurpose $purpose): ?CommunicationPermission
    {
        return CommunicationPermission::query()
            ->where('unit_business_context_id', $contextId)
            ->where('unit_contact_context_link_id', $contactId)
            ->where('product_id', $productId)
            ->where('purpose', $purpose->value)
            ->where('sender_scope', (string) config('ai-sales.outreach.sender_scope'))
            ->where('status', CommunicationPermissionStatus::Granted->value)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->latest('id')->first();
    }

    public function endpointHash(string $address): string
    {
        return hash('sha256', Str::lower(trim($address)));
    }

    private function assertContact(Unit $unit, UnitBusinessContext $context, UnitContactContextLink $contact): void
    {
        if ((int) $contact->unit_id !== (int) $unit->id || (int) $contact->unit_business_context_id !== (int) $context->id || $contact->archived_at) {
            throw new PolicyViolation('permission_contact_scope_mismatch', 'Contact link does not belong to the selected Unit context.');
        }
        $contact->loadMissing('email');
    }

    private function recordDecision(
        CommunicationPermission $permission,
        ?CommunicationPermissionStatus $from,
        CommunicationPermissionStatus $to,
        string $reason,
        User $actor,
        ?string $note = null,
    ): void {
        $payload = [
            'permission_public_id' => $permission->public_id, 'from' => $from?->value,
            'to' => $to->value, 'reason' => $reason, 'note' => $note,
            'evidence_set_hash' => $permission->evidence_set_hash, 'actor_id' => $actor->id,
            'sequence' => $permission->decisions()->count() + 1,
        ];
        CommunicationPermissionDecision::query()->create([
            'communication_permission_id' => $permission->id, 'from_status' => $from?->value,
            'to_status' => $to->value, 'reason_code' => $reason, 'safe_note' => $note,
            'evidence_set_hash' => $permission->evidence_set_hash, 'decision_hash' => AiCanonicalJson::hash($payload),
            'decided_by' => $actor->id, 'decided_at' => now(),
        ]);
    }
}
