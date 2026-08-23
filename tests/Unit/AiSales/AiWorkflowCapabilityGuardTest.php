<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Workflows\AiWorkflowCapabilityGuard;
use App\Domain\AiSales\Workflows\AiWorkflowDefinition;
use App\Domain\AiSales\Workflows\AiWorkflowRegistry;
use App\Domain\AiSales\Workflows\AiWorkflowStepDefinition;
use Tests\TestCase;

class AiWorkflowCapabilityGuardTest extends TestCase
{
    public function test_workflow_registry_is_deterministic_and_rejects_unknown_or_provider_reordered_plan(): void
    {
        $registry = new AiWorkflowRegistry;
        $workflow = $registry->all()[0];

        $this->assertSame('synthetic.good_context_classification.v1', $workflow->code);
        $this->assertSame(['catalog.get_synthetic_good'], collect($workflow->steps)->pluck('toolCode')->all());
        $this->assertFalse($workflow->requiresProviderNativeTools);
        $this->assertFalse($workflow->liveEligible);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $workflow->workflowHash);
        $this->assertSame($workflow->workflowHash, (new AiWorkflowRegistry)->all()[0]->workflowHash);

        try {
            $registry->get('attacker.selected.workflow', '1');
            $this->fail('Unknown workflow was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('unknown_workflow_blocked', $violation->errorCode);
        }
    }

    public function test_timeweb_local_strict_or_native_profiles_and_luna_native_tools_are_blocked(): void
    {
        $guard = new AiWorkflowCapabilityGuard;

        try {
            $guard->assertCompatible(
                $this->workflow(AiProcessingContour::LocalRu, ['chat_completions', 'strict_structured_outputs']),
                'timeweb',
                'timeweb/gpt-oss-120b',
            );
            $this->fail('Unverified local strict schema capability was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('timeweb_local_capability_unverified', $violation->errorCode);
        }

        try {
            $guard->assertCompatible(
                $this->workflow(AiProcessingContour::ExternalSanitized, ['responses_api', 'function_calling'], true),
                'timeweb',
                'openai/gpt-5.6-luna',
            );
            $this->fail('Luna native tool calling was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('workflow_native_tools_unsupported', $violation->errorCode);
        }
    }

    public function test_registered_runtime_has_no_proxyapi_or_cross_contour_fallback_provider(): void
    {
        $providers = app(AiProviderRegistry::class)->all();

        $this->assertSame(['fake', 'fake'], array_map(static fn ($provider) => $provider->code(), $providers));
        $this->assertSame(['local_ru', 'external_sanitized'], array_map(static fn ($provider) => $provider->route()->value, $providers));
        $this->assertNotContains('proxyapi', array_map(static fn ($provider) => $provider->code(), $providers));
    }

    private function workflow(
        AiProcessingContour $contour,
        array $capabilities,
        bool $native = false,
    ): AiWorkflowDefinition {
        return new AiWorkflowDefinition(
            'test.capability.workflow',
            '1',
            'Test-only capability definition.',
            ['test:1'],
            [AiTaskProfile::PublicCompanyResearch],
            [AiPurpose::UnitResearch],
            [AiAudience::Internal],
            [BusinessLane::Sales],
            [UnitRoleCode::Customer],
            $contour,
            $capabilities,
            [new AiWorkflowStepDefinition(1, 'catalog.get_synthetic_good', '1', ['sku' => 'SYN-001'], 'stop_on_failure')],
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => ['summary' => ['type' => 'string', 'maxLength' => 1000]],
            ],
            1,
            8_192,
            5_000,
            1_000,
            '0.0000',
            false,
            $native,
            true,
            true,
            false,
        );
    }
}
