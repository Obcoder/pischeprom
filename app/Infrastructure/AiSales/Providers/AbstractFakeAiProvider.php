<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Contracts\FakeAiProviderInterface;
use App\Domain\AiSales\DTO\Providers\AiProviderError;
use App\Domain\AiSales\DTO\Providers\AiProviderOutputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiProviderToolCall;
use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\DTO\Providers\ProviderCapabilityProfile;
use App\Domain\AiSales\DTO\Providers\ProviderHealthResult;
use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Enums\FakeAiProviderScenario;
use Illuminate\Support\Carbon;

abstract class AbstractFakeAiProvider implements FakeAiProviderInterface
{
    public function __construct(protected readonly FakeAiProviderScenario $scenario = FakeAiProviderScenario::Normal) {}

    public function code(): string
    {
        return 'fake';
    }

    abstract public function route(): AiProviderRoute;

    abstract protected function modelId(): string;

    protected function capabilityCodes(): array
    {
        return ['chat_completions', 'strict_structured_outputs', 'function_calling', 'usage_reporting', 'request_id'];
    }

    public function capabilities(AiModelProfile $modelProfile): ProviderCapabilityProfile
    {
        $capabilities = [];

        foreach ($this->capabilityCodes() as $capability) {
            $capabilities[$capability] = AiCapabilityVerificationStatus::SyntheticTested;
        }

        return new ProviderCapabilityProfile(
            $this->code(),
            $this->route(),
            $this->modelId(),
            $this->route()->contour(),
            $capabilities,
            16_000,
            4_000,
            Carbon::parse('2026-08-15T00:00:00+03:00'),
            null,
        );
    }

    public function supports(AiRequestRequirements $requirements): bool
    {
        return $this->capabilities(AiModelProfile::StandardResearch)->supports($requirements);
    }

    public function healthCheck(): ProviderHealthResult
    {
        $available = $this->scenario !== FakeAiProviderScenario::ProviderUnavailable;

        return new ProviderHealthResult(
            $available,
            $available ? 'available' : 'unavailable',
            $available ? 'Deterministic fake provider is ready.' : 'Deterministic fake provider is unavailable.',
            now()->toISOString(),
        );
    }

    public function createResponse(AiProviderRequest $request): AiProviderResponse
    {
        if ($request->contour !== $this->route()->contour()) {
            return $this->failure($request, AiProviderErrorCategory::ContourBlocked, 'fake_contour_mismatch');
        }

        if ($this->route() === AiProviderRoute::ExternalSanitized && $request->containsLocalOnlyData) {
            return $this->failure($request, AiProviderErrorCategory::ContourBlocked, 'fake_external_local_only_rejected');
        }

        return match ($this->scenario) {
            FakeAiProviderScenario::Normal => $this->success($request, [
                new AiProviderOutputItem('structured', $this->normalStructuredOutput($request)),
            ]),
            FakeAiProviderScenario::StructuredOutput => $this->success($request, [
                new AiProviderOutputItem('structured', ['summary' => 'Synthetic structured response.']),
            ]),
            FakeAiProviderScenario::FunctionCall => $this->success($request, [], [
                new AiProviderToolCall(
                    'fake-call-'.substr($request->sanitizedPayloadHash, 0, 12),
                    'units.get_sanitized_dossier_profile',
                    '1',
                    ['unit_reference' => hash('sha256', $request->runPublicId)],
                    hash('sha256', $request->runPublicId.':unit_profile'),
                ),
            ], AiProviderResponseStatus::RequiresAction),
            FakeAiProviderScenario::Timeout => $this->failure($request, AiProviderErrorCategory::Timeout, 'fake_timeout', true),
            FakeAiProviderScenario::RateLimited => $this->failure($request, AiProviderErrorCategory::RateLimited, 'fake_rate_limited', true),
            FakeAiProviderScenario::ServerError => $this->failure($request, AiProviderErrorCategory::ServerError, 'fake_server_error', true),
            FakeAiProviderScenario::SchemaMismatch => $this->failure($request, AiProviderErrorCategory::SchemaMismatch, 'fake_schema_mismatch'),
            FakeAiProviderScenario::DlpBlock => $this->failure($request, AiProviderErrorCategory::DlpBlocked, 'fake_dlp_block'),
            FakeAiProviderScenario::ContourBlock => $this->failure($request, AiProviderErrorCategory::ContourBlocked, 'fake_contour_block'),
            FakeAiProviderScenario::ProviderUnavailable => $this->failure($request, AiProviderErrorCategory::ProviderUnavailable, 'fake_provider_unavailable', true),
        };
    }

    private function success(
        AiProviderRequest $request,
        array $outputs,
        array $toolCalls = [],
        AiProviderResponseStatus $status = AiProviderResponseStatus::Completed,
    ): AiProviderResponse {
        return new AiProviderResponse(
            $status,
            $this->code(),
            $this->route()->value,
            $this->modelId(),
            $this->requestId($request),
            $outputs,
            $toolCalls,
            [],
            new AiProviderUsage(120, 40, 0, 0, 0, count($toolCalls), null, null, '0.0000'),
        );
    }

    private function failure(
        AiProviderRequest $request,
        AiProviderErrorCategory $category,
        string $code,
        bool $retryable = false,
    ): AiProviderResponse {
        return new AiProviderResponse(
            AiProviderResponseStatus::Failed,
            $this->code(),
            $this->route()->value,
            $this->modelId(),
            $this->requestId($request),
            [],
            [],
            [],
            new AiProviderUsage,
            new AiProviderError($category, $code, 'Deterministic fake provider failure.', $retryable),
        );
    }

    private function requestId(AiProviderRequest $request): string
    {
        return 'fake-'.substr(hash('sha256', $request->runPublicId.':'.$request->stepSequence.':'.$this->route()->value), 0, 24);
    }

    private function normalStructuredOutput(AiProviderRequest $request): array
    {
        if (! array_key_exists('factor_candidates', (array) ($request->responseSchema['properties'] ?? []))) {
            return ['summary' => 'Synthetic fake response.'];
        }

        $references = [];
        foreach ($request->inputItems as $item) {
            if ($item->label === 'stage10_product_evidence') {
                $references = array_values(array_filter((array) ($item->data['evidence_references'] ?? []), 'is_string'));
            }
        }

        return [
            'factor_candidates' => $references === [] ? [] : [[
                'factor_code' => 'direct_product_mention',
                'evidence_reference_ids' => [$references[0]],
                'polarity' => 'positive',
                'claim' => 'Synthetic evidence candidate requires deterministic validation.',
                'contradiction' => false,
                'model_confidence' => 50,
            ]],
            'missing_evidence' => $references === [] ? ['direct_product_mention'] : [],
        ];
    }
}
