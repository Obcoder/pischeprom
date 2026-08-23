<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Workflows\AiWorkflowExecutor;

class AiToolingDiagnosticsAuthorizationTest extends Stage07TestCase
{
    public function test_tooling_diagnostics_require_authentication_and_explicit_permission_and_are_read_only(): void
    {
        $this->getJson('/api/ai-sales/tooling')->assertUnauthorized();

        $withoutPermission = $this->aiUser(['sales']);
        $this->actingAs($withoutPermission)->getJson('/api/ai-sales/tooling')->assertForbidden();

        $viewer = $this->workflowUser(['sales']);
        $response = $this->actingAs($viewer)->getJson('/api/ai-sales/tooling?tool_code=database.query&contour=local_ru')
            ->assertOk()
            ->assertJsonPath('data.manual_execution', 'synthetic_cli_or_tests_only')
            ->assertJsonPath('data.features.external_http_enabled', false)
            ->assertJsonPath('data.features.failover_enabled', false)
            ->assertJsonPath('data.features.provider_native_tools_enabled', false)
            ->assertJsonPath('data.features.live_business_workflows_enabled', false);

        $toolCodes = collect($response->json('data.tools'))->pluck('code');
        $this->assertContains('catalog.search_public_goods', $toolCodes);
        $this->assertContains('crm.propose_entity_candidate', $toolCodes);
        $this->assertNotContains('database.query', $toolCodes);
        $this->assertSame(['synthetic.good_context_classification.v1'], collect($response->json('data.workflows'))->pluck('code')->all());
        $this->assertStringNotContainsString('handler_class', $response->getContent());
        $this->assertStringNotContainsString('fixedInput', $response->getContent());

        $this->actingAs($viewer)->postJson('/api/ai-sales/tooling', [
            'tool_code' => 'catalog.get_synthetic_good',
            'arguments' => ['sku' => 'SYN-001'],
            'prompt' => 'run it',
            'provider' => 'timeweb',
            'url' => 'https://example.invalid',
        ])->assertMethodNotAllowed();
    }

    public function test_dual_lane_execution_diagnostics_are_filtered_server_side(): void
    {
        ['run' => $salesRun] = $this->preparedSyntheticRun(
            actor: $this->workflowUser(['sales']),
            lane: 'sales',
            role: 'customer',
            idempotency: 'stage07-diagnostic-sales',
        );
        ['run' => $procurementRun] = $this->preparedSyntheticRun(
            actor: $this->workflowUser(['procurement']),
            lane: 'procurement',
            role: 'supplier',
            idempotency: 'stage07-diagnostic-procurement',
        );
        app(AiWorkflowExecutor::class)->execute($this->workflowContext($salesRun, 'diagnostic-sales'));
        app(AiWorkflowExecutor::class)->execute($this->workflowContext($procurementRun, 'diagnostic-procurement'));

        $salesViewer = $this->workflowUser(['sales']);
        $salesExecutions = $this->actingAs($salesViewer)
            ->getJson('/api/ai-sales/tooling')
            ->assertOk()
            ->json('data.executions');

        $this->assertSame([$salesRun->public_id], collect($salesExecutions)->pluck('run_id')->all());
        $this->assertNotContains($procurementRun->public_id, collect($salesExecutions)->pluck('run_id')->all());
    }
}
