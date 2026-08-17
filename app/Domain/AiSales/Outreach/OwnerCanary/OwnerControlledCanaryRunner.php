<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\CommunicationPermissionService;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachNormalizedEventService;
use App\Models\CommunicationPermission;
use App\Models\Email;
use App\Models\MailingEvent;
use App\Models\OutreachDispatch;
use App\Models\OutreachFollowUpPlan;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class OwnerControlledCanaryRunner
{
    /** @var list<string> */
    private const ACTOR_PERMISSIONS = [
        'ai_sales.view',
        'ai_sales.sales.view',
        'ai_sales.outreach.view',
        'ai_sales.outreach.draft',
        'ai_sales.outreach.review',
        'ai_sales.outreach.claims.review',
        'ai_sales.communication_permissions.view',
        'ai_sales.communication_permissions.manage',
        'ai_sales.communication_suppressions.manage',
        'ai_sales.outreach.dispatch.view',
        'ai_sales.outreach.dispatch.prepare',
        'ai_sales.outreach.dispatch.queue',
        'ai_sales.outreach.dispatch.cancel',
        'ai_sales.outreach.events.view',
    ];

    public function __construct(
        private readonly OwnerControlledCanaryContract $contract,
        private readonly OutreachDraftService $drafts,
        private readonly CommunicationPermissionService $permissions,
        private readonly OutreachDispatchService $dispatches,
        private readonly OutreachNormalizedEventService $events,
    ) {}

    /** @return array<string, mixed> */
    public function dryRun(OwnerControlledCanaryConfiguration $configuration): array
    {
        $this->assertNoPriorCanary();
        DB::beginTransaction();

        try {
            $fixture = $this->createReviewedFixture($configuration);
            $prepared = $this->dispatches->prepare(
                $fixture['draft']->fresh(),
                $fixture['actor']->fresh(),
                OwnerControlledCanaryContract::SCENARIO,
            );
            if (! $prepared->accepted) {
                throw new PolicyViolation('stage13b_prepare_revalidation_failed', 'The prepare checkpoint did not pass.');
            }

            $result = $this->safeResult($prepared->dispatch->fresh(), $fixture['permission']->fresh(), 0);
            DB::rollBack();

            return [
                ...$result,
                'mode' => 'dry_run',
                'provider_called' => false,
                'rolled_back' => true,
            ];
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** @return array<string, mixed> */
    public function live(OwnerControlledCanaryConfiguration $configuration, int $observeSeconds): array
    {
        $this->assertNoPriorCanary();
        $fixture = DB::transaction(fn (): array => $this->createReviewedFixture($configuration));
        $permission = $fixture['permission']->fresh();
        $dispatch = null;
        $httpGuard = null;

        try {
            $prepared = $this->dispatches->prepare(
                $fixture['draft']->fresh(),
                $fixture['actor']->fresh(),
                OwnerControlledCanaryContract::SCENARIO,
            );
            if (! $prepared->accepted) {
                throw new PolicyViolation('stage13b_prepare_revalidation_failed', 'The prepare checkpoint did not pass.');
            }

            $dispatch = $prepared->dispatch->fresh(['mailMessage', 'sending', 'contactLink.email']);
            $httpGuard = new OwnerControlledCanaryHttpGuard(
                (string) config('services.unisender_go.api_key'),
                $configuration->recipient,
                $dispatch,
            );
            Http::globalRequestMiddleware($httpGuard->authorize(...));

            $queued = $this->dispatches->queue($dispatch, $fixture['actor']->fresh());
            if (! $queued->accepted) {
                throw new PolicyViolation('stage13b_queue_revalidation_failed', 'The queue checkpoint did not pass.');
            }

            $dispatch = $dispatch->fresh(['mailMessage', 'sending', 'decisions']);
            $observed = $this->observeNormalizedEvents($dispatch, $observeSeconds);
            $result = [
                ...$this->safeResult($dispatch, $permission, $observed['count']),
                ...$httpGuard->summary(),
                'mode' => 'live',
                'provider_called' => $httpGuard->summary()['provider_send_requests'] === 1,
                'normalized_event_statuses' => $observed['statuses'],
                'raw_provider_fields_null' => $observed['raw_fields_null'],
                'rolled_back' => false,
            ];
        } finally {
            if ($permission->fresh()->status === CommunicationPermissionStatus::Granted) {
                $this->permissions->revoke(
                    $permission->fresh(),
                    $fixture['actor']->fresh(),
                    'stage13b_canary_complete',
                    'Owner-controlled canary permission closed after the single attempt.',
                );
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return [
            ...$result,
            'permission_status_after' => $permission->fresh()->status->value,
        ];
    }

    /** @return array{actor: User, permission: CommunicationPermission, draft: \App\Models\OutreachDraft} */
    private function createReviewedFixture(OwnerControlledCanaryConfiguration $configuration): array
    {
        $actor = $this->actor();
        $unit = Unit::query()->create([
            'name' => OwnerControlledCanaryContract::UNIT_NAME,
            'is_customer' => false,
            'is_supplier' => false,
        ]);
        $context = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
            'stage' => 'qualified',
            'status' => 'active',
            'confidence' => 100,
            'owner_user_id' => $actor->id,
            'reviewer_user_id' => $actor->id,
            'source' => 'stage13b-owner-controlled-canary',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => OwnerControlledCanaryContract::PRODUCT_NAME,
            'eng' => 'Synthetic broccoli canary',
            'is_published' => false,
        ]);
        $matchReference = 'code-owned:stage13b:synthetic-broccoli-product-match';
        $match = UnitProductMatch::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'product_id' => $product->id,
            'match_type' => 'potential_need',
            'status' => 'approved',
            'origin' => 'manual',
            'evidence_confidence' => 100,
            'safe_rationale' => 'Code-owned fictional Product relevance for the owner-controlled canary only.',
            'evidence_reference' => $matchReference,
            'evidence_hash' => hash('sha256', $matchReference),
            'rules_version' => 'stage13b-canary-v1',
            'created_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'stale_after' => now()->addDay(),
        ]);
        $email = Email::query()->create([
            'address' => $configuration->recipient,
            'source' => 'owner_controlled_stage13b_canary',
            'is_active' => true,
        ]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        $contact = UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'channel_type' => 'email',
            'email_id' => $email->id,
            'channel_value_snapshot' => 'owner-controlled-canary-email',
            'normalized_hash' => hash('sha256', mb_strtolower($configuration->recipient)),
            'contact_role' => 'business_general',
            'verification_status' => ObservationVerificationStatus::Verified,
            'confidence' => 100,
            'data_classification' => DataClassification::PersonalData,
            'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required',
            'review_required' => true,
            'last_verified_at' => now(),
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'created_by' => $actor->id,
        ]);

        $draft = $this->drafts->create($actor, $unit, $context, [
            'unit_contact_context_link_id' => $contact->id,
            'unit_product_match_id' => $match->id,
            'purpose' => 'advertising_outreach',
        ]);
        $revision = $this->drafts->revise(
            $draft,
            $actor,
            $this->contract->content($match),
        );
        $permission = $this->permissions->create($actor, $unit, $context, $contact->fresh('email'), [
            'purpose' => 'advertising_outreach',
            'product_id' => $product->id,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addHours(OwnerControlledCanaryContract::PERMISSION_HOURS),
            'evidence' => [[
                'type' => 'other_reviewed',
                'reference' => $configuration->permissionEvidenceReference,
                'content_hash' => $configuration->permissionEvidenceSha256,
                'captured_at' => now(),
                'source_controller' => 'stage13b_owner_canary_cli',
                'safe_note' => 'Owner-reviewed evidence for one controlled mailbox canary.',
            ]],
        ]);
        $permission = $this->permissions->review(
            $permission,
            $actor,
            CommunicationPermissionStatus::Granted,
            'owner_reviewed_canary_evidence',
            'One message only; no follow-up.',
        );
        foreach (OutreachReviewType::cases() as $type) {
            $this->drafts->review(
                $draft->fresh(),
                $revision,
                $actor,
                $type,
                OutreachReviewDecision::Approved,
                'owner_controlled_canary_review',
                'Synthetic canary revision reviewed for one controlled recipient.',
            );
        }

        return [
            'actor' => $actor,
            'permission' => $permission->fresh(),
            'draft' => $draft->fresh(),
        ];
    }

    private function actor(): User
    {
        $actor = User::query()->create([
            'name' => 'Stage 13B Synthetic Canary Reviewer',
            'email' => 'stage13b-canary-'.Str::lower((string) Str::ulid()).'@invalid.example',
            'password' => Hash::make(Str::random(64)),
            'type' => 'employee',
            'status' => 'active',
            'account_type' => 'individual',
        ]);
        $actor->forceFill(['email_verified_at' => now()])->save();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::ACTOR_PERMISSIONS as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'crm']);
        }
        $actor->givePermissionTo(self::ACTOR_PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor->fresh();
    }

    private function assertNoPriorCanary(): void
    {
        if (Unit::query()->where('name', OwnerControlledCanaryContract::UNIT_NAME)->exists()) {
            throw new PolicyViolation('stage13b_previous_canary_exists', 'A prior or partial Stage 13B canary blocks every additional attempt.');
        }
    }

    /** @return array{count: int, statuses: list<string>, raw_fields_null: bool} */
    private function observeNormalizedEvents(OutreachDispatch $dispatch, int $seconds): array
    {
        $deadline = microtime(true) + $seconds;
        $events = collect();

        do {
            $events = MailingEvent::query()
                ->where('sending_id', $dispatch->sending_id)
                ->orderBy('id')
                ->get();
            foreach ($events as $event) {
                $this->events->apply($event);
            }
            if ($events->isNotEmpty() || microtime(true) >= $deadline) {
                break;
            }
            usleep(1_000_000);
        } while (true);

        $eventIds = $events->pluck('id');
        $rawFieldsNull = $eventIds->isEmpty() || ! DB::table('mailing_events')
            ->whereIn('id', $eventIds)
            ->where(function ($query): void {
                foreach (['email', 'url', 'delivery_status', 'destination_response', 'user_agent', 'ip', 'country', 'city', 'sender_ip', 'metadata', 'payload'] as $column) {
                    $query->orWhereNotNull($column);
                }
            })->exists();

        return [
            'count' => $events->count(),
            'statuses' => $events->pluck('normalized_status')->filter()->unique()->values()->all(),
            'raw_fields_null' => $rawFieldsNull,
        ];
    }

    /** @return array<string, mixed> */
    private function safeResult(OutreachDispatch $dispatch, CommunicationPermission $permission, int $eventCount): array
    {
        $dispatch->loadMissing(['sending', 'mailMessage', 'decisions']);
        $checkpoints = $dispatch->decisions->pluck('checkpoint')->map(
            fn ($value): string => is_object($value) && property_exists($value, 'value') ? $value->value : (string) $value,
        )->unique()->values()->all();

        return [
            'scenario' => OwnerControlledCanaryContract::SCENARIO,
            'unit_created' => 1,
            'entity_created' => 0,
            'good_created' => 0,
            'prospecting_candidates_created' => 0,
            'draft_public_id' => $dispatch->draft()->value('public_id'),
            'revision_public_id' => $dispatch->revision()->value('public_id'),
            'dispatch_public_id' => $dispatch->public_id,
            'permission_public_id' => $permission->public_id,
            'permission_valid_until' => $permission->valid_until?->toIso8601String(),
            'permission_status_before_cleanup' => $permission->status->value,
            'purpose' => $dispatch->purpose->value,
            'dispatch_state' => $dispatch->state->value,
            'sending_status' => $dispatch->sending?->status,
            'mail_message_count' => $dispatch->mail_message_id ? 1 : 0,
            'sending_count' => $dispatch->sending_id ? 1 : 0,
            'request_profile' => $dispatch->request_profile,
            'revalidation_checkpoints' => $checkpoints,
            'normalized_event_count' => $eventCount,
            'follow_up_plans' => OutreachFollowUpPlan::query()->where('outreach_dispatch_id', $dispatch->id)->count(),
            'raw_outbound_payloads_null' => $dispatch->sending
                && $dispatch->sending->request_payload === null
                && $dispatch->sending->response_payload === null
                && $dispatch->sending->failed_emails === null
                && $dispatch->sending->error_message === null,
        ];
    }
}
