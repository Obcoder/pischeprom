<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\OutreachDispatchEligibilityService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachSafeDto;
use App\Infrastructure\AiSales\Providers\TimewebExternalSanitizedProvider;
use App\Models\AuthorizedMailDispatchAttempt;
use App\Models\CommunicationPermission;
use App\Models\CommunicationSuppression;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Good;
use App\Models\OutreachDispatchDecision;
use App\Models\OutreachDraft;
use App\Models\OutreachDraftClaim;
use App\Models\OutreachDraftReview;
use App\Models\OutreachDraftRevision;
use App\Models\Product;
use App\Models\ProspectingCandidate;
use App\Models\Sending;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitContactContextLink;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class OutreachCanaryRunner
{
    private ?OutreachCanaryHttpGuard $httpGuard = null;

    public function __construct(
        private readonly OutreachDraftService $drafts,
        private readonly OutreachDispatchEligibilityService $eligibility,
        private readonly OutreachCanaryContract $contract,
        private readonly TimewebExternalSanitizedProvider $provider,
    ) {}

    /** @return array<string, mixed> */
    public function run(bool $live, string $apiKey): array
    {
        $this->httpGuard = null;
        [$actor, $draft, $recipientMarker] = $this->createFixture();
        $httpGuard = new OutreachCanaryHttpGuard($apiKey, $recipientMarker, $this->contract);
        $this->httpGuard = $httpGuard;
        if ($live) {
            Http::globalRequestMiddleware(fn ($request) => $httpGuard->authorize($request));
        }
        $draft->load([
            'unit', 'businessContext', 'contactLink.email', 'productMatch.product',
            'goodMatch.good', 'productRelevanceSnapshot', 'goodFitSnapshot', 'prospectPrioritySnapshot',
        ]);

        $dto = OutreachSafeDto::fromDraft($draft);
        $request = $this->contract->buildRequest($dto, $recipientMarker);
        $this->contract->authorizeRequest($request);
        $summary = $this->contract->inputSummary($request);

        $capability = $this->provider->capabilities(AiModelProfile::OutreachDrafting);
        if ($capability->modelId !== OutreachCanaryContract::MODEL_ID
            || ! $capability->supports($request->requirements)) {
            throw new PolicyViolation('stage12b_capability_authorization_blocked', 'The exact Luna capability profile does not authorize this canary.');
        }

        $response = $live ? $this->provider->createResponse($request) : $this->contract->fakeResponse();
        if ($response->status === AiProviderResponseStatus::Failed && $response->error) {
            throw new PolicyViolation('stage12b_'.$response->error->safeCode, 'The provider returned a normalized safe error.');
        }
        $normalized = $this->contract->normalizeResponse($response, $draft, $recipientMarker);
        $revision = $this->drafts->appendLiveSyntheticCanaryRevision(
            $draft->fresh(),
            $actor->fresh(),
            $normalized['content'],
            $summary['input_hash'],
            $live,
        );
        $eligibility = $this->eligibility->evaluate($draft->fresh(), $actor->fresh(), persist: true);

        if ($eligibility->eligible
            || ! in_array('scoped_permission_missing', $eligibility->blockReasons, true)
            || ! in_array('stage12_dispatch_not_implemented', $eligibility->blockReasons, true)
            || $revision->dlp_status->value !== 'passed') {
            throw new PolicyViolation('stage12b_dispatch_boundary_failed', 'Dispatch or permission remained unexpectedly eligible.');
        }

        $this->assertExpectedDatabaseState($draft, $revision, $recipientMarker, $apiKey);

        return [
            'scenario' => OutreachCanaryContract::SCENARIO,
            'outcome' => 'generated_valid',
            'provider_called' => $live,
            'provider' => 'timeweb',
            'route' => 'external_sanitized',
            'model' => OutreachCanaryContract::MODEL_ID,
            'endpoint' => 'responses',
            'schema_profile' => OutreachCanaryContract::SCHEMA_PROFILE,
            'strict_schema' => true,
            'store' => false,
            'native_tools' => false,
            'previous_response_id' => false,
            ...$summary,
            'provider_status' => $normalized['response_status'],
            'safe_request_id_hash' => $normalized['request_id_hash'],
            'usage' => $normalized['usage'],
            'validation' => [
                'schema' => 'passed',
                'claims' => 'passed',
                'evidence_binding' => 'passed',
                'commercial_facts' => 'passed',
                'dlp' => $revision->dlp_status->value,
                'prompt_injection' => 'passed',
                'recipient_excluded' => true,
            ],
            'renderer' => [
                'version' => $revision->renderer_version,
                'hash' => $revision->renderer_hash,
                'subject_hash' => hash('sha256', $revision->subject),
                'plaintext_hash' => hash('sha256', $revision->plaintext),
                'plaintext_bytes' => strlen($revision->plaintext),
                'html_hash' => hash('sha256', $revision->html),
                'html_bytes' => strlen($revision->html),
            ],
            'counts' => [
                'units' => Unit::query()->count(),
                'entities' => Entity::query()->without(['buildings', 'classification', 'country'])->count(),
                'products' => Product::query()->without(['category', 'manufacturers'])->count(),
                'goods' => Good::query()->count(),
                'drafts' => OutreachDraft::query()->count(),
                'revisions' => OutreachDraftRevision::query()->count(),
                'claims' => OutreachDraftClaim::query()->count(),
                'reviews' => OutreachDraftReview::query()->count(),
                'permissions' => CommunicationPermission::query()->count(),
                'suppressions' => CommunicationSuppression::query()->count(),
                'dispatch_decisions' => OutreachDispatchDecision::query()->count(),
                'sendings' => Sending::query()->count(),
                'authorized_mail_dispatch_attempts' => AuthorizedMailDispatchAttempt::query()->count(),
                'prospecting_candidates' => ProspectingCandidate::query()->count(),
            ],
            'permission' => 'unknown_evidence_required',
            'suppression' => 'none',
            'dispatch_eligible' => false,
            'dispatch_block_reasons' => $eligibility->blockReasons,
            'unit_changes_outside_temp_fixture' => 0,
            'entity_changes' => 0,
            'recipient_persisted_only_in_temp_email_row' => true,
            'raw_provider_body_persisted' => false,
            ...$httpGuard->summary(),
        ];
    }

    /** @return array<string, int> */
    public function httpSummary(): array
    {
        return $this->httpGuard?->summary() ?? [
            'timeweb_requests' => 0,
            'yandex_requests' => 0,
            'other_live_http' => 0,
            'retries' => 0,
            'failovers' => 0,
        ];
    }

    /** @return array{User, OutreachDraft, string} */
    private function createFixture(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.outreach.view',
            'ai_sales.outreach.draft',
        ];
        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'crm']);
        }
        $actor = User::query()->create([
            'name' => 'Stage 12B Synthetic Reviewer',
            'email' => 'stage12b-reviewer-'.Str::lower(Str::random(12)).'@operator.invalid',
            'password' => Hash::make(Str::random(64)),
            'type' => 'employee',
            'status' => 'active',
            'account_type' => 'individual',
            'email_verified_at' => now(),
        ]);
        $actor->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $unit = Unit::query()->create([
            'name' => 'Тестовая фабрика готовых блюд «Синтетика»',
            'is_customer' => false,
            'is_supplier' => false,
        ]);
        $context = UnitBusinessContext::query()->create([
            'unit_id' => $unit->id,
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
            'stage' => 'qualified',
            'status' => 'active',
            'source' => 'stage12b-code-owned-synthetic',
            'created_by' => $actor->id,
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Брокколи',
            'eng' => 'Broccoli',
            'is_published' => true,
        ]);
        $evidenceHash = hash(
            'sha256',
            OutreachCanaryContract::EVIDENCE_CLAIM.'|'.OutreachCanaryContract::EVIDENCE_INDICATOR,
        );
        $match = UnitProductMatch::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'product_id' => $product->id,
            'match_type' => 'potential_need',
            'status' => 'approved',
            'origin' => 'manual',
            'evidence_confidence' => 100,
            'safe_rationale' => OutreachCanaryContract::EVIDENCE_INDICATOR,
            'evidence_reference' => OutreachCanaryContract::EVIDENCE_REFERENCE,
            'evidence_hash' => $evidenceHash,
            'created_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'stale_after' => now()->addDays(14),
        ]);

        $recipientMarker = 'stage12b-'.Str::lower(Str::random(24)).'@recipient.invalid';
        $email = Email::query()->create([
            'address' => $recipientMarker,
            'source' => 'synthetic_fixture',
            'is_active' => true,
        ]);
        $unit->emails()->syncWithoutDetaching([$email->id]);
        $contact = UnitContactContextLink::query()->create([
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context->id,
            'channel_type' => 'email',
            'email_id' => $email->id,
            'channel_value_snapshot' => 'synthetic-corporate-email',
            'normalized_hash' => hash('sha256', $recipientMarker),
            'contact_role' => 'business_general',
            'verification_status' => ObservationVerificationStatus::Verified,
            'data_classification' => DataClassification::PersonalData,
            'visibility_scope' => UnitVisibilityScope::SalesLane,
            'communication_state' => 'review_required',
            'review_required' => true,
            'last_verified_at' => now(),
            'created_by' => $actor->id,
        ]);
        $draft = $this->drafts->create($actor, $unit, $context, [
            'unit_contact_context_link_id' => $contact->id,
            'unit_product_match_id' => $match->id,
            'purpose' => 'advertising_outreach',
        ]);

        return [$actor, $draft, $recipientMarker];
    }

    private function assertExpectedDatabaseState(
        OutreachDraft $draft,
        OutreachDraftRevision $revision,
        string $recipientMarker,
        string $apiKey,
    ): void {
        $expectedCounts = [
            OutreachDraft::class => 1,
            OutreachDraftRevision::class => 1,
            OutreachDraftClaim::class => 1,
            OutreachDraftReview::class => 0,
            OutreachDispatchDecision::class => 1,
            CommunicationPermission::class => 0,
            CommunicationSuppression::class => 0,
            Sending::class => 0,
            AuthorizedMailDispatchAttempt::class => 0,
            ProspectingCandidate::class => 0,
            Entity::class => 0,
            Good::class => 0,
        ];
        foreach ($expectedCounts as $model => $expected) {
            $query = $model === Entity::class
                ? Entity::query()->without(['buildings', 'classification', 'country'])
                : $model::query();
            if ($query->count() !== $expected) {
                throw new PolicyViolation('stage12b_database_boundary_failed', 'Unexpected rows appeared in the isolated canary database.');
            }
        }
        if (Unit::query()->count() !== 1
            || Product::query()->without(['category', 'manufacturers'])->count() !== 1
            || Email::query()->count() !== 1
            || Email::query()->value('address') !== $recipientMarker
            || (int) $revision->outreach_draft_id !== (int) $draft->id) {
            throw new PolicyViolation('stage12b_fixture_boundary_failed', 'The fixed synthetic fixture boundary was not preserved.');
        }

        $persisted = json_encode([
            'draft' => $draft->only([
                'public_id', 'purpose', 'status', 'generation_origin', 'template_profile', 'template_version',
                'template_hash', 'policy_hash', 'input_hash', 'evidence_hash',
            ]),
            'revision' => $revision->only([
                'public_id', 'structured_content', 'subject', 'plaintext', 'html', 'renderer_version',
                'renderer_hash', 'dlp_status', 'dlp_findings', 'dlp_hash', 'claim_set_hash', 'input_hash',
            ]),
            'claims' => $revision->claims->map->only([
                'claim_type', 'text_fragment_hash', 'evidence_type', 'evidence_reference', 'evidence_hash',
                'evidence_status', 'review_status', 'safe_rationale', 'audit_hash',
            ])->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (str_contains($persisted, $recipientMarker)
            || ($apiKey !== '' && str_contains($persisted, $apiKey))) {
            throw new PolicyViolation('stage12b_unsafe_persistence_detected', 'Secret or recipient data appeared in draft persistence.');
        }

        foreach (['outreach_drafts', 'outreach_draft_revisions', 'outreach_draft_claims'] as $table) {
            if (collect(Schema::getColumnListing($table))->contains(
                fn (string $column): bool => preg_match('/(?:raw|provider|request_body|response_body)/i', $column) === 1,
            )) {
                throw new PolicyViolation('stage12b_raw_body_column_detected', 'Outreach persistence exposes a raw provider body column.');
            }
        }
    }
}
