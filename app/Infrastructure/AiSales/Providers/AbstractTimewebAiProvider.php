<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Contracts\TimewebAiProviderInterface;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\DTO\Providers\ProviderCapabilityProfile;
use App\Domain\AiSales\DTO\Providers\ProviderHealthResult;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;
use App\Infrastructure\AiSales\Timeweb\TimewebAiGatewayConfiguration;
use App\Infrastructure\AiSales\Timeweb\TimewebAiGatewayTransport;
use App\Infrastructure\AiSales\Timeweb\TimewebCapabilityProfileFactory;
use App\Infrastructure\AiSales\Timeweb\TimewebModelSelector;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeBudgetGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeCostEstimator;
use App\Infrastructure\AiSales\Timeweb\TimewebProviderResponseNormalizer;
use App\Infrastructure\AiSales\Timeweb\TimewebRequestMapper;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticDlpGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebTransportException;
use App\Models\AiProviderModel;
use Throwable;

abstract class AbstractTimewebAiProvider implements TimewebAiProviderInterface
{
    public function __construct(
        private readonly TimewebAiGatewayConfiguration $configuration,
        private readonly TimewebAiGatewayTransport $transport,
        private readonly TimewebModelSelector $models,
        private readonly TimewebCapabilityProfileFactory $capabilityProfiles,
        private readonly TimewebSyntheticDlpGuard $dlp,
        private readonly TimewebRequestMapper $mapper,
        private readonly TimewebProviderResponseNormalizer $normalizer,
        private readonly TimewebProbeBudgetGuard $budget,
        private readonly TimewebProbeCostEstimator $costs,
        private readonly AiResidencyAuthorizationService $residency,
    ) {}

    public function code(): string
    {
        return 'timeweb';
    }

    abstract public function route(): AiProviderRoute;

    public function capabilities(AiModelProfile $modelProfile): ProviderCapabilityProfile
    {
        return $this->capabilityProfiles->make(
            $this->route(),
            $this->models->modelForProfile($this->route(), $modelProfile),
        );
    }

    public function supports(AiRequestRequirements $requirements): bool
    {
        foreach (AiModelProfile::cases() as $profile) {
            try {
                if ($this->capabilities($profile)->supports($requirements)) {
                    return true;
                }
            } catch (PolicyViolation) {
                // A missing logical model mapping remains fail-closed.
            }
        }

        return false;
    }

    public function healthCheck(): ProviderHealthResult
    {
        try {
            $this->configuration->assertProbeReady($this->route());
            $available = $this->models->allowedModels($this->route()) !== [];
        } catch (Throwable) {
            $available = false;
        }

        return new ProviderHealthResult(
            $available,
            $available ? 'configured' : 'blocked',
            $available
                ? 'Timeweb synthetic-only route is configured; no health HTTP request was made.'
                : 'Timeweb synthetic-only route is not safely configured.',
            now()->toISOString(),
        );
    }

    public function createResponse(AiProviderRequest $request): AiProviderResponse
    {
        $modelId = $this->models->modelForProfile($this->route(), $request->modelProfile);

        try {
            $this->configuration->assertProbeReady($this->route());
            $this->dlp->authorize($request);
            $this->models->assertAllowed($this->route(), $modelId);

            if ($this->route() === AiProviderRoute::LocalRu) {
                $this->residency->authorize('timeweb', $this->route()->value, $modelId);
            }

            $inventory = AiProviderModel::query()
                ->where('provider_code', 'timeweb')
                ->where('provider_route', $this->route()->value)
                ->where('model_id', $modelId)
                ->where('active_in_inventory', true)
                ->first();

            if (! $inventory || $inventory->endpoint_profile === AiProviderEndpointProfile::Unsupported) {
                throw new PolicyViolation('timeweb_endpoint_profile_unverified', 'Exact model endpoint profile is not verified.');
            }

            $reservedCost = $this->costs->maximum(
                $this->route(),
                $modelId,
                $request->requirements->maxInputTokens,
                $request->requirements->maxOutputTokens,
            );
            $this->budget->reserve(
                $request->requirements->maxInputTokens,
                $request->requirements->maxOutputTokens,
                $reservedCost,
            );

            $response = match ($inventory->endpoint_profile) {
                AiProviderEndpointProfile::ChatCompletions => $this->normalizer->chat(
                    $this->transport->chatCompletions(
                        $this->route(),
                        $this->mapper->chatCompletions($request, $modelId),
                        $this->budget->remainingTimeoutSeconds(),
                    ),
                    $this->route(),
                    $modelId,
                ),
                AiProviderEndpointProfile::Responses => $this->normalizer->responses(
                    $this->transport->responses(
                        $this->route(),
                        $this->mapper->responses($request, $modelId),
                        $this->budget->remainingTimeoutSeconds(),
                    ),
                    $this->route(),
                    $modelId,
                ),
                AiProviderEndpointProfile::Unsupported => throw new PolicyViolation(
                    'timeweb_endpoint_profile_unverified',
                    'Exact model endpoint profile is not verified.',
                ),
            };
            $this->budget->reconcile($response->usage);

            return $this->withEstimatedCost(
                $response,
                $this->costs->actualOrReserved($this->route(), $modelId, $response->usage, $reservedCost),
            );
        } catch (TimewebTransportException $exception) {
            return $this->normalizer->failure($exception, $this->route(), $modelId);
        }
    }

    private function withEstimatedCost(AiProviderResponse $response, string $normalizedRub): AiProviderResponse
    {
        $usage = new AiProviderUsage(
            $response->usage->inputTokens,
            $response->usage->outputTokens,
            $response->usage->reasoningTokens,
            $response->usage->cachedTokens,
            $response->usage->searchCount,
            $response->usage->toolCallCount,
            $response->usage->providerAmount,
            $response->usage->providerCurrency,
            $normalizedRub,
        );

        return new AiProviderResponse(
            $response->status,
            $response->providerCode,
            $response->providerRoute,
            $response->modelId,
            $response->requestId,
            $response->outputItems,
            $response->toolCalls,
            $response->citations,
            $usage,
            $response->error,
        );
    }
}
