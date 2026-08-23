<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiRequestRequirements;
use App\Domain\AiSales\DTO\Routing\AiProcessingRouteDecision;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\FakeAiProviderScenario;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Providers\AiProviderRouter;
use App\Domain\AiSales\Services\AiProviderCapabilityAuthorizationService;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;
use App\Infrastructure\AiSales\Providers\FailingFakeAiProvider;
use App\Infrastructure\AiSales\Providers\FakeExternalSanitizedAiProvider;

class AiProviderRouterTest extends Stage04TestCase
{
    public function test_unavailable_local_route_is_blocked_without_external_fallback(): void
    {
        $verifier = $this->manager();
        $this->verifyLocalResidency($verifier);
        $registry = new AiProviderRegistry;
        $registry->register(new FailingFakeAiProvider(
            AiProviderRoute::LocalRu,
            FakeAiProviderScenario::ProviderUnavailable,
        ));
        $registry->register(new FakeExternalSanitizedAiProvider);
        $router = $this->router($registry);

        try {
            $router->select(
                $this->decision(AiProcessingContour::LocalRu),
                AiModelProfile::StandardResearch,
                new AiRequestRequirements(['chat_completions'], 100, 100, false),
            );
            $this->fail('Unavailable local route must not fall back to external.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('provider_unavailable', $violation->errorCode);
        }
    }

    public function test_router_preserves_policy_contour_and_selection_disables_fallback(): void
    {
        $registry = new AiProviderRegistry;
        $registry->register(new \App\Infrastructure\AiSales\Providers\FakeLocalRuAiProvider);
        $registry->register(new FakeExternalSanitizedAiProvider);
        $router = $this->router($registry);
        $decision = $this->decision(AiProcessingContour::ExternalSanitized);
        $requirements = new AiRequestRequirements(
            ['chat_completions', 'strict_structured_outputs'],
            100,
            100,
            true,
        );
        $selection = $router->select($decision, AiModelProfile::StandardResearch, $requirements);

        $this->assertSame(AiProviderRoute::ExternalSanitized, $selection->route);
        $this->assertSame(AiProcessingContour::ExternalSanitized, $selection->contour);
        $this->assertFalse($selection->fallbackAllowed);

        try {
            $router->createResponse(
                $selection,
                $decision,
                $this->request(AiProcessingContour::LocalRu, $requirements, $decision->decisionHash),
            );
            $this->fail('Router must reject a contour-mutated request.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('router_contour_mutation_blocked', $violation->errorCode);
        }
    }

    private function router(AiProviderRegistry $registry): AiProviderRouter
    {
        return new AiProviderRouter(
            $registry,
            app(AiProviderCapabilityAuthorizationService::class),
            app(AiResidencyAuthorizationService::class),
        );
    }

    private function decision(AiProcessingContour $contour): AiProcessingRouteDecision
    {
        return new AiProcessingRouteDecision(
            AiProcessingDecision::Allow,
            AiPurpose::UnitResearch,
            AiAudience::Internal,
            BusinessLane::Sales,
            UnitRoleCode::Customer,
            10,
            20,
            ['public' => 6],
            ['shared_public' => 6],
            $contour,
            'allowed',
            0,
            false,
            'stage03-v1',
            'stage03-v1',
            'stage04-v1',
        );
    }

    private function request(
        AiProcessingContour $contour,
        AiRequestRequirements $requirements,
        string $policyHash,
    ): AiProviderRequest {
        return new AiProviderRequest(
            '00000000-0000-4000-8000-000000000002',
            1,
            $contour,
            AiModelProfile::StandardResearch,
            [new AiProviderInputItem('sanitized_data', 'synthetic', ['name' => 'Unit'])],
            ['type' => 'object'],
            [],
            $requirements,
            hash('sha256', 'idempotency'),
            $policyHash,
            hash('sha256', 'prompt'),
            hash('sha256', 'schema'),
            hash('sha256', 'payload'),
            ['public' => 1],
            false,
            5,
        );
    }
}
