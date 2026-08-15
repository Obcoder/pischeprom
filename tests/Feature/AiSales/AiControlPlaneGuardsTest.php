<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Runs\ExecuteAiAgentRunStep;
use App\Domain\AiSales\Runs\PrepareAiAgentRun;
use App\Jobs\AiSales\ExecuteAiAgentRunJob;
use App\Models\AiAgentRun;
use App\Models\AiControlSetting;
use App\Models\AiProviderCapability;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

class AiControlPlaneGuardsTest extends Stage04TestCase
{
    public function test_queued_job_reauthorizes_actor_and_inactive_context_before_any_provider_use(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'Reauthorization Unit']);
        $context = $this->createContext($manager, $unit, ['lane' => 'sales', 'role_code' => 'customer']);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        $run = $this->createRun($actor, $definition->code, $unit->id, $context['id'], 'reauthorize-run');

        $actor->revokePermissionTo('ai_sales.sales.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->execute($run);

        $this->assertSame('blocked_by_policy', $run->fresh()->status->value);
        $this->assertSame('run_reauthorization_failed', $run->fresh()->safe_error_code);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $run->id]);

        $secondActor = $this->aiUser(['sales']);
        $second = $this->createRun($secondActor, $definition->code, $unit->id, $context['id'], 'inactive-context-run');
        $unit->businessContexts()->whereKey($context['id'])->update(['status' => 'paused']);
        $this->execute($second);

        $this->assertSame('blocked_by_policy', $second->fresh()->status->value);
        $this->assertSame('unit_context_inactive', $second->fresh()->safe_error_code);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $second->id]);
    }

    public function test_cancellation_kill_switch_and_zero_egress_guard_are_fail_closed(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'Guarded Unit']);
        $context = $this->createContext($manager, $unit, ['lane' => 'sales', 'role_code' => 'customer']);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        $run = $this->createRun($actor, $definition->code, $unit->id, $context['id'], 'cancel-before-worker');

        $this->actingAs($actor)->postJson("/api/ai-sales/runs/{$run->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->execute($run);
        $this->assertSame('cancelled', $run->fresh()->status->value);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $run->id]);

        AiControlSetting::query()->where('key', 'kill_switch.external_sanitized')->update(['boolean_value' => true]);
        $this->actingAs($actor)->postJson('/api/ai-sales/runs', $this->payload(
            $definition->code,
            $unit->id,
            $context['id'],
            'kill-switch-block',
        ))->assertUnprocessable()->assertJsonPath('code', 'ai_kill_switch_active');

        AiControlSetting::query()->where('key', 'kill_switch.external_sanitized')->delete();
        $this->actingAs($actor)->postJson('/api/ai-sales/runs', $this->payload(
            $definition->code,
            $unit->id,
            $context['id'],
            'missing-kill-switch-block',
        ))->assertUnprocessable()->assertJsonPath('code', 'ai_kill_switch_active');

        AiControlSetting::query()->create(['key' => 'kill_switch.external_sanitized', 'boolean_value' => false]);
        config()->set('ai-sales.external_calls_enabled', true);
        $this->actingAs($actor)->postJson('/api/ai-sales/runs', $this->payload(
            $definition->code,
            $unit->id,
            $context['id'],
            'external-egress-block',
        ))->assertUnprocessable()->assertJsonPath('code', 'external_egress_forbidden_stage04');
    }

    public function test_budget_and_persisted_capability_verification_block_without_retry_or_fallback(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'Capability Unit']);
        $context = $this->createContext($manager, $unit, ['lane' => 'sales', 'role_code' => 'customer']);
        $definition = $this->enableDefinition('unit_public_research_synthetic');

        config()->set('ai-sales.limits.max_tokens', 100);
        $budgetRun = $this->createRun($actor, $definition->code, $unit->id, $context['id'], 'token-budget-run');
        $this->execute($budgetRun);
        $budgetRun = $budgetRun->fresh()->load('steps');
        $this->assertSame('budget_exceeded', $budgetRun->status->value);
        $this->assertSame(0, $budgetRun->steps->first()->retry_count);
        $this->assertSame(0, $budgetRun->steps->first()->failover_count);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $budgetRun->id]);

        config()->set('ai-sales.limits.max_tokens', 4_000);
        AiProviderCapability::query()
            ->where('provider_code', 'fake')
            ->where('provider_route', 'external_sanitized')
            ->where('capability', 'strict_structured_outputs')
            ->update(['status' => 'suspended']);
        $capabilityRun = $this->createRun($actor, $definition->code, $unit->id, $context['id'], 'capability-block-run');
        $this->execute($capabilityRun);
        $capabilityRun = $capabilityRun->fresh()->load('steps');

        $this->assertSame('provider_unavailable', $capabilityRun->status->value);
        $this->assertSame('provider_capability_unverified', $capabilityRun->safe_error_code);
        $this->assertSame(0, $capabilityRun->steps->first()->retry_count);
        $this->assertSame(0, $capabilityRun->steps->first()->failover_count);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $capabilityRun->id]);
    }

    public function test_timeweb_synthetic_transport_cannot_be_selected_by_unit_run_api(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'No Timeweb Domain Runtime Unit']);
        $context = $this->createContext($manager, $unit, ['lane' => 'sales', 'role_code' => 'customer']);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        config()->set('ai-sales.transport_mode', 'timeweb_synthetic_only');

        $this->actingAs($actor)->postJson('/api/ai-sales/runs', $this->payload(
            $definition->code,
            $unit->id,
            $context['id'],
            'timeweb-domain-runtime-block',
        ))->assertUnprocessable()->assertJsonPath('code', 'timeweb_domain_runtime_blocked');

        $this->assertDatabaseCount('ai_agent_runs', 0);
    }

    private function createRun($actor, string $definitionCode, int $unitId, int $contextId, string $key): AiAgentRun
    {
        $id = $this->actingAs($actor)->postJson(
            '/api/ai-sales/runs',
            $this->payload($definitionCode, $unitId, $contextId, $key),
        )->assertCreated()->json('data.id');

        return AiAgentRun::query()->where('public_id', $id)->firstOrFail();
    }

    private function payload(string $definitionCode, int $unitId, int $contextId, string $key): array
    {
        return [
            'definition_code' => $definitionCode,
            'definition_version' => '1',
            'unit_id' => $unitId,
            'unit_business_context_id' => $contextId,
            'idempotency_key' => $key,
        ];
    }

    private function execute(AiAgentRun $run): void
    {
        (new ExecuteAiAgentRunJob($run->id))->handle(
            app(PrepareAiAgentRun::class),
            app(ExecuteAiAgentRunStep::class),
        );
    }
}
