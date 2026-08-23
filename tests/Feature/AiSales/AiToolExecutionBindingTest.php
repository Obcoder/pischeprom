<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolExecutor;
use App\Domain\AiSales\Tools\AiToolPolicyGuard;
use App\Domain\AiSales\Tools\AiToolRegistry;
use App\Domain\AiSales\Tools\AiToolRequest;
use App\Domain\AiSales\Workflows\AiWorkflowRegistry;
use App\Models\AiAgentRun;
use App\Models\AiPolicyDecisionRecord;

class AiToolExecutionBindingTest extends Stage07TestCase
{
    public function test_wrong_unit_context_dimensions_workflow_hash_and_stale_lock_are_blocked_before_handler(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-binding-run');
        $workflow = app(AiWorkflowRegistry::class)->all()[0];
        $request = new AiToolRequest(
            'catalog.get_synthetic_good',
            '1',
            ['sku' => 'SYN-001'],
            'binding-test',
        );
        $overrides = [
            ['unitId' => $run->unit_id + 1000],
            ['contextId' => $run->unit_business_context_id + 1000],
            ['lane' => BusinessLane::Procurement],
            ['role' => UnitRoleCode::Supplier],
            ['purpose' => AiPurpose::SupplierDiscovery],
            ['audience' => AiAudience::Supplier],
            ['lock' => $run->lock_version + 1],
            ['workflowHash' => str_repeat('f', 64)],
        ];

        foreach ($overrides as $index => $override) {
            try {
                app(AiToolExecutor::class)->execute(
                    $this->toolContext($run, $workflow->workflowHash, $override),
                    new AiToolRequest(
                        $request->toolCode,
                        $request->toolVersion,
                        $request->input,
                        'binding-test-'.$index,
                    ),
                );
                $this->fail('Stale or mismatched tool binding was accepted.');
            } catch (PolicyViolation $violation) {
                $this->assertContains($violation->errorCode, [
                    'tool_execution_binding_mismatch',
                    'tool_workflow_binding_mismatch',
                ]);
            }
        }

        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $run->id]);
    }

    public function test_local_only_and_opposite_lane_tools_are_rejected_by_policy_before_query(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-contour-run');
        $step = $run->steps->first();
        $policy = $this->policy($run);
        config()->set('ai-sales.live_business_workflows_enabled', true);
        $guard = app(AiToolPolicyGuard::class);
        $registry = app(AiToolRegistry::class);
        $context = new AiToolExecutionContext(
            $run->id,
            $step->id,
            $run->initiator_user_id,
            $run->unit_id,
            $run->unit_business_context_id,
            $run->lane,
            $run->role_code,
            $run->purpose,
            $run->audience,
            AiProcessingContour::ExternalSanitized,
            'future.business.workflow',
            '1',
            str_repeat('d', 64),
            $policy->id,
            $policy->decision_hash,
            AiCanonicalJson::hash([]),
            $run->lock_version,
            1,
            4_096,
            5_000,
            '0.0000',
            false,
        );

        try {
            $guard->authorize($registry->get('unit.get_business_context_summary', '1'), $context, $run, $step);
            $this->fail('Local-only tool was authorized for external contour.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('tool_policy_dimension_blocked', $violation->errorCode);
        }

        try {
            $guard->authorize($registry->get('sales.get_aggregate_demand_summary', '1'), $context, $run, $step);
            $this->fail('Procurement aggregate was authorized in a sales lane.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('tool_policy_dimension_blocked', $violation->errorCode);
        }

        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $run->id]);
    }

    public function test_disabled_pricing_and_proposal_tools_block_before_handler(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-disabled-tools-run');
        $step = $run->steps->first();
        $policy = $this->policy($run);
        config()->set('ai-sales.live_business_workflows_enabled', true);
        $guard = app(AiToolPolicyGuard::class);
        $registry = app(AiToolRegistry::class);
        $context = new AiToolExecutionContext(
            $run->id,
            $step->id,
            $run->initiator_user_id,
            $run->unit_id,
            $run->unit_business_context_id,
            $run->lane,
            $run->role_code,
            $run->purpose,
            $run->audience,
            $run->selected_contour,
            'future.business.workflow',
            '1',
            str_repeat('e', 64),
            $policy->id,
            $policy->decision_hash,
            AiCanonicalJson::hash([]),
            $run->lock_version,
            0,
            1_024,
            5_000,
            '0.0000',
            false,
        );

        foreach (['pricing.get_customer_offer_summary', 'crm.propose_entity_candidate'] as $code) {
            try {
                $guard->authorize($registry->get($code, '1'), $context, $run, $step);
                $this->fail("Disabled tool {$code} was authorized.");
            } catch (PolicyViolation $violation) {
                $this->assertSame('tool_disabled', $violation->errorCode);
            }
        }

        $this->assertDatabaseMissing('entity_candidate_proposals', ['unit_id' => $run->unit_id]);
    }

    private function toolContext(AiAgentRun $run, string $workflowHash, array $override): AiToolExecutionContext
    {
        $policy = $this->policy($run);

        return new AiToolExecutionContext(
            $run->id,
            $run->steps->first()->id,
            $run->initiator_user_id,
            $override['unitId'] ?? $run->unit_id,
            $override['contextId'] ?? $run->unit_business_context_id,
            $override['lane'] ?? $run->lane,
            $override['role'] ?? $run->role_code,
            $override['purpose'] ?? $run->purpose,
            $override['audience'] ?? $run->audience,
            $run->selected_contour,
            'synthetic.good_context_classification.v1',
            '1',
            $override['workflowHash'] ?? $workflowHash,
            $policy->id,
            $policy->decision_hash,
            AiCanonicalJson::hash(['sku' => 'SYN-001']),
            $override['lock'] ?? $run->lock_version,
            1,
            8_192,
            5_000,
            '0.0000',
            true,
        );
    }

    private function policy(AiAgentRun $run): AiPolicyDecisionRecord
    {
        return AiPolicyDecisionRecord::query()
            ->where('ai_agent_run_id', $run->id)
            ->where('ai_agent_run_step_id', $run->steps->first()->id)
            ->latest('id')
            ->firstOrFail();
    }
}
