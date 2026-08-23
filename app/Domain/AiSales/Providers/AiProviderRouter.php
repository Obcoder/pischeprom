<?php

namespace App\Domain\AiSales\Providers;

use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\DTO\Providers\ProviderSelectionDecision;
use App\Domain\AiSales\DTO\Routing\AiProcessingRouteDecision;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiProviderCapabilityAuthorizationService;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;

class AiProviderRouter
{
    public function __construct(
        private readonly AiProviderRegistry $providers,
        private readonly AiProviderCapabilityAuthorizationService $capabilities,
        private readonly AiResidencyAuthorizationService $residency,
    ) {}

    public function select(
        AiProcessingRouteDecision $contourDecision,
        AiModelProfile $modelProfile,
        AiRequestRequirements $requirements,
    ): ProviderSelectionDecision {
        if (! $contourDecision->permitsProviderSelection()) {
            throw new PolicyViolation('contour_decision_not_permitted', 'Provider selection requires an allow/redact contour decision.');
        }

        $route = match ($contourDecision->selectedContour) {
            AiProcessingContour::LocalRu => AiProviderRoute::LocalRu,
            AiProcessingContour::ExternalSanitized => AiProviderRoute::ExternalSanitized,
            AiProcessingContour::None => throw new PolicyViolation('contour_none_blocked', 'The NONE contour cannot select a provider.'),
        };
        $provider = $this->providers->forRoute($route);
        $profile = $provider->capabilities($modelProfile);

        if ($profile->contour !== $contourDecision->selectedContour || $profile->route !== $route) {
            throw new PolicyViolation('provider_contour_mismatch', 'Provider selection cannot change the processing contour.');
        }

        if (! $profile->supports($requirements) || ! $provider->supports($requirements)) {
            throw new PolicyViolation('provider_capability_unverified', 'Required provider/model capabilities are not in a verified state.');
        }

        $this->capabilities->authorize(
            $provider->code(),
            $route,
            $profile->modelId,
            $profile->contour,
            $requirements,
        );

        $health = $provider->healthCheck();

        if (! $health->available) {
            throw new PolicyViolation('provider_unavailable', 'The selected provider is unavailable.');
        }

        if ($route === AiProviderRoute::LocalRu) {
            $this->residency->authorize($provider->code(), $route->value, $profile->modelId);
        }

        return new ProviderSelectionDecision(
            $provider->code(),
            $route,
            $profile->modelId,
            $profile->contour,
            $requirements->capabilities,
            'exact_contour_provider_selected',
            false,
        );
    }

    public function createResponse(
        ProviderSelectionDecision $selection,
        AiProcessingRouteDecision $contourDecision,
        AiProviderRequest $request,
    ): AiProviderResponse {
        if ($selection->contour !== $contourDecision->selectedContour
            || $request->contour !== $contourDecision->selectedContour
            || $selection->route->contour() !== $request->contour) {
            throw new PolicyViolation('router_contour_mutation_blocked', 'Router and request must preserve the precomputed contour.');
        }

        if ($request->policyDecisionHash !== $contourDecision->decisionHash) {
            throw new PolicyViolation('policy_decision_hash_mismatch', 'Provider request is not bound to the current contour decision.');
        }

        $provider = $this->providers->forRoute($selection->route);

        if ($provider->code() !== $selection->providerCode) {
            throw new PolicyViolation('provider_selection_mismatch', 'Selected provider is no longer registered for the route.');
        }

        return $provider->createResponse($request);
    }
}
