<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\Enums\AiCapabilitySupportStatus;
use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeCostEstimator;
use App\Models\AiProviderCapability;
use App\Models\AiProviderModel;
use App\Models\AiProviderPricingSnapshot;
use Illuminate\Support\Carbon;

final class OutreachCanaryEvidenceService
{
    public const PRICING_VERSION = 'stage05b-luna-2026-08-16';

    public const PRICING_REFERENCE = 'panel-review:timeweb-gpt-5.6-luna-pricing-2026-08-16';

    public const PRICING_SHA256 = '633aeb6d014e384ebe0e8a9befc8f97a4d6a3822ce340275b78cc754c3d71a42';

    public const ACCEPTED_HANDOFF_REFERENCE = 'accepted-handoff:stage05b-timeweb-live-synthetic-acceptance-2026-08-16';

    private const VERIFIED_AT = '2026-08-16T00:00:00+03:00';

    private const EXPIRES_AT = '2026-09-15T00:00:00+03:00';

    public function __construct(private readonly TimewebProbeCostEstimator $costs) {}

    public function installAcceptedEphemeralEvidence(): void
    {
        if (! app()->environment('testing')
            || ! config('ai-sales.outreach.live_synthetic_canary_enabled', false)
            || config('database.default') !== 'sqlite') {
            throw new PolicyViolation('stage12b_evidence_install_blocked', 'Accepted evidence may be loaded only into the testing canary SQLite process.');
        }

        $verifiedAt = Carbon::parse(self::VERIFIED_AT);
        $expiresAt = Carbon::parse(self::EXPIRES_AT);
        $handoffHash = hash('sha256', self::ACCEPTED_HANDOFF_REFERENCE.'|'.OutreachCanaryContract::MODEL_ID);

        AiProviderModel::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => 'external_sanitized',
            'model_id' => OutreachCanaryContract::MODEL_ID,
            'display_label' => OutreachCanaryContract::MODEL_ID,
            'endpoint_profile' => AiProviderEndpointProfile::Responses,
            'active_in_inventory' => true,
            'first_seen_at' => $verifiedAt,
            'last_seen_at' => $verifiedAt,
            'safe_metadata' => ['source' => 'accepted_stage05b_handoff', 'raw_body_stored' => false],
            'source_reference' => self::ACCEPTED_HANDOFF_REFERENCE,
            'metadata_hash' => $handoffHash,
            'created_by_reference' => 'stage12b-ephemeral-import',
            'updated_by_reference' => 'stage12b-ephemeral-import',
        ]);

        foreach ([
            'responses' => [AiCapabilitySupportStatus::Supported, AiCapabilityVerificationStatus::SyntheticTested],
            'strict_structured_outputs' => [AiCapabilitySupportStatus::Supported, AiCapabilityVerificationStatus::SyntheticTested],
            'store_false' => [AiCapabilitySupportStatus::Supported, AiCapabilityVerificationStatus::SyntheticTested],
            'usage_reporting' => [AiCapabilitySupportStatus::Supported, AiCapabilityVerificationStatus::SyntheticTested],
            'request_id' => [AiCapabilitySupportStatus::Unknown, AiCapabilityVerificationStatus::Unknown],
            'function_calling' => [AiCapabilitySupportStatus::Unsupported, AiCapabilityVerificationStatus::SyntheticTested],
            'hosted_web_search' => [AiCapabilitySupportStatus::Unsupported, AiCapabilityVerificationStatus::Documented],
        ] as $capability => [$support, $status]) {
            $resultHash = hash('sha256', implode('|', [
                self::ACCEPTED_HANDOFF_REFERENCE,
                OutreachCanaryContract::MODEL_ID,
                $capability,
                $support->value,
                $status->value,
            ]));
            AiProviderCapability::query()->create([
                'provider_code' => 'timeweb',
                'provider_route' => 'external_sanitized',
                'model_id' => OutreachCanaryContract::MODEL_ID,
                'contour' => AiProcessingContour::ExternalSanitized,
                'capability' => $capability,
                'status' => $status,
                'support_state' => $support,
                'max_context_tokens' => 30_000,
                'max_output_tokens' => 8_000,
                'evidence_reference' => self::ACCEPTED_HANDOFF_REFERENCE.':'.$capability,
                'evidence_hash' => $resultHash,
                'evidence_source' => 'accepted_stage05b_handoff',
                'safe_request_id' => null,
                'adapter_version' => 'stage05-v1',
                'policy_version' => 'stage05-synthetic-only-v1',
                'schema_version' => 'stage05-probe-v1',
                'result_hash' => $resultHash,
                'operator_reference' => 'stage12b-ephemeral-import',
                'verified_by' => null,
                'verified_at' => $verifiedAt,
                'expires_at' => $expiresAt,
                'probe_version' => 'timeweb-stage05b-accepted-v1',
            ]);
        }

        AiProviderPricingSnapshot::query()->create([
            'provider_code' => 'timeweb',
            'provider_route' => 'external_sanitized',
            'model_id' => OutreachCanaryContract::MODEL_ID,
            'version' => self::PRICING_VERSION,
            'currency' => 'RUB',
            'input_per_million' => '135.000000',
            'output_per_million' => '810.000000',
            'reasoning_per_million' => null,
            'effective_at' => $verifiedAt,
            'expires_at' => $expiresAt,
            'source_reference' => self::PRICING_REFERENCE,
            'source_hash' => self::PRICING_SHA256,
            'recorded_by_reference' => 'stage05b-owner-reviewed',
        ]);
    }

    /** @return array<string, mixed> */
    public function assertReady(): array
    {
        $model = AiProviderModel::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', 'external_sanitized')
            ->where('model_id', OutreachCanaryContract::MODEL_ID)
            ->where('active_in_inventory', true)
            ->first();
        if (! $model || $model->endpoint_profile !== AiProviderEndpointProfile::Responses
            || ! hash_equals(self::ACCEPTED_HANDOFF_REFERENCE, (string) $model->source_reference)) {
            throw new PolicyViolation('stage12b_inventory_evidence_missing', 'The accepted exact Luna inventory evidence is missing.');
        }

        $capabilities = AiProviderCapability::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', 'external_sanitized')
            ->where('model_id', OutreachCanaryContract::MODEL_ID)
            ->get()
            ->keyBy('capability');
        foreach (['responses', 'strict_structured_outputs', 'store_false'] as $required) {
            $record = $capabilities->get($required);
            if (! $record
                || $record->support_state !== AiCapabilitySupportStatus::Supported
                || ! $record->status->verified()
                || ! $record->verified_at
                || ! $record->expires_at
                || $record->expires_at->isPast()
                || (int) $record->max_context_tokens < OutreachCanaryContract::MAX_INPUT_TOKENS
                || (int) $record->max_output_tokens < OutreachCanaryContract::MAX_OUTPUT_TOKENS
                || ! preg_match('/^[a-f0-9]{64}$/D', (string) $record->evidence_hash)) {
                throw new PolicyViolation('stage12b_capability_evidence_stale', 'Required Luna capability evidence is missing or stale.');
            }
        }
        if (($capabilities->get('function_calling')?->support_state ?? AiCapabilitySupportStatus::Unknown)
            !== AiCapabilitySupportStatus::Unsupported) {
            throw new PolicyViolation('stage12b_native_tools_not_blocked', 'Native tool capability must remain unsupported.');
        }

        $pricing = AiProviderPricingSnapshot::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', 'external_sanitized')
            ->where('model_id', OutreachCanaryContract::MODEL_ID)
            ->where('version', self::PRICING_VERSION)
            ->first();
        if (! $pricing
            || (string) $pricing->input_per_million !== '135.000000'
            || (string) $pricing->output_per_million !== '810.000000'
            || $pricing->reasoning_per_million !== null
            || ! hash_equals(self::PRICING_REFERENCE, (string) $pricing->source_reference)
            || ! hash_equals(self::PRICING_SHA256, (string) $pricing->source_hash)
            || ! $pricing->expires_at
            || $pricing->expires_at->isPast()) {
            throw new PolicyViolation('stage12b_pricing_evidence_stale', 'The immutable Luna pricing snapshot is missing or stale.');
        }

        $reserved = $this->costs->maximum(
            AiProviderRoute::ExternalSanitized,
            OutreachCanaryContract::MODEL_ID,
            min(
                max(1, (int) config('ai-sales.providers.timeweb.probe.max_input_tokens')),
                OutreachCanaryContract::MAX_INPUT_TOKENS,
            ),
            min(
                max(1, (int) config('ai-sales.providers.timeweb.probe.max_output_tokens')),
                OutreachCanaryContract::MAX_OUTPUT_TOKENS,
            ),
        );
        $configuredRubCap = (float) config('ai-sales.providers.timeweb.probe.max_rub', 0);
        if ((float) $reserved > (float) OutreachCanaryContract::MAX_RUB
            || $configuredRubCap <= 0
            || (float) $reserved > $configuredRubCap) {
            throw new PolicyViolation('stage12b_pricing_budget_exceeded', 'The worst-case Luna price exceeds the canary RUB cap.');
        }

        return [
            'inventory' => 'active_exact_model',
            'endpoint_profile' => 'responses',
            'capabilities' => $capabilities->mapWithKeys(static fn (AiProviderCapability $record): array => [
                $record->capability => [
                    'support' => $record->support_state->value,
                    'lifecycle' => $record->status->value,
                    'expires_at' => $record->expires_at?->toISOString(),
                ],
            ])->all(),
            'pricing_snapshot_version' => self::PRICING_VERSION,
            'pricing_source_reference' => self::PRICING_REFERENCE,
            'reasoning_price' => 'unknown_not_zero',
            'worst_case_reserved_rub' => $reserved,
            'production_approved' => false,
        ];
    }
}
