<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\FakeAiProviderScenario;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Runs\ExecuteAiAgentRunStep;
use App\Domain\AiSales\Runs\PrepareAiAgentRun;
use App\Infrastructure\AiSales\Providers\FakeExternalSanitizedAiProvider;
use App\Infrastructure\AiSales\Providers\FakeLocalRuAiProvider;
use App\Jobs\AiSales\ExecuteAiAgentRunJob;
use App\Models\AiAgentRun;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class AiControlPlaneWorkflowTest extends Stage04TestCase
{
    public function test_external_sanitized_fake_run_is_idempotent_audited_and_persists_no_raw_output(): void
    {
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'Synthetic Public Unit']);
        $context = $this->createContext($manager, $unit, [
            'lane' => 'sales',
            'role_code' => 'prospective_customer',
        ]);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        $entitiesBefore = Entity::query()->count();
        $payload = [
            'definition_code' => $definition->code,
            'definition_version' => $definition->version,
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context['id'],
            'idempotency_key' => 'synthetic-external-0001',
        ];

        $created = $this->actingAs($actor)->postJson('/api/ai-sales/runs', $payload)->assertCreated();
        $run = AiAgentRun::query()->where('public_id', $created->json('data.id'))->firstOrFail();
        $this->execute($run);
        $run = $run->fresh()->load('steps');

        $this->assertSame('completed', $run->status->value);
        $this->assertSame('external_sanitized', $run->selected_contour->value);
        $this->assertSame('fake', $run->actual_provider);
        $this->assertSame('external_sanitized', $run->actual_route);
        $this->assertSame(0, $run->steps->first()->failover_count);
        $this->assertDatabaseHas('ai_policy_decisions', [
            'ai_agent_run_id' => $run->id,
            'decision' => 'allow',
            'contour' => 'external_sanitized',
        ]);
        $this->assertDatabaseHas('ai_data_access_logs', [
            'ai_agent_run_id' => $run->id,
            'source_type' => 'unit_shared_public_profile',
            'decision' => 'allow',
        ]);
        $this->assertDatabaseHas('ai_usage_records', [
            'ai_agent_run_id' => $run->id,
            'provider' => 'fake',
            'provider_route' => 'external_sanitized',
            'operation' => 'ai_sales_synthetic_response',
            'status' => 'success',
        ]);
        $this->assertSame($entitiesBefore, Entity::query()->count());

        $this->actingAs($actor)->postJson('/api/ai-sales/runs', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $run->public_id);
        $this->assertSame(1, AiAgentRun::query()->where('idempotency_key', $run->idempotency_key)->count());

        $persisted = json_encode([
            DB::table('ai_agent_runs')->where('id', $run->id)->first(),
            DB::table('ai_agent_run_steps')->where('ai_agent_run_id', $run->id)->get(),
            DB::table('ai_usage_records')->where('ai_agent_run_id', $run->id)->get(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(
            (string) config('ai-sales.prompt_registry.unit_public_research_synthetic.template'),
            $persisted,
        );
        $this->assertStringNotContainsString('Synthetic fake response.', $persisted);
    }

    public function test_local_ru_requires_current_human_residency_and_never_falls_back_external(): void
    {
        $manager = $this->manager();
        $actor = $this->aiUser(['procurement']);
        $unit = $this->unit(['name' => 'Synthetic Local Unit']);
        $context = $this->createContext($manager, $unit, [
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);
        $definition = $this->enableDefinition('unit_internal_summary_synthetic');
        $verification = $this->verifyLocalResidency($manager);
        $verification->update(['expires_at' => now()->subMinute()]);

        $blocked = $this->createRun($actor, $definition->code, $definition->version, $unit->id, $context['id'], 'synthetic-local-blocked');
        $this->execute($blocked);
        $blocked = $blocked->fresh();

        $this->assertSame('residency_unverified', $blocked->status->value);
        $this->assertNull($blocked->actual_provider);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $blocked->id]);

        $verification->update(['verified_at' => now(), 'expires_at' => now()->addDay(), 'status' => 'verified']);
        $allowed = $this->createRun($actor, $definition->code, $definition->version, $unit->id, $context['id'], 'synthetic-local-allowed');
        $this->execute($allowed);
        $allowed = $allowed->fresh()->load('steps');

        $this->assertSame('completed', $allowed->status->value);
        $this->assertSame('local_ru', $allowed->selected_contour->value);
        $this->assertSame('local_ru', $allowed->actual_route);
        $this->assertSame(0, $allowed->steps->first()->failover_count);
        $this->assertDatabaseMissing('ai_usage_records', [
            'ai_agent_run_id' => $allowed->id,
            'provider_route' => 'external_sanitized',
        ]);
    }

    public function test_fake_tool_request_stops_for_local_authorization_without_side_effects(): void
    {
        Queue::fake();
        $registry = new AiProviderRegistry;
        $registry->register(new FakeLocalRuAiProvider);
        $registry->register(new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::FunctionCall));
        $this->app->instance(AiProviderRegistry::class, $registry);
        $manager = $this->manager();
        $actor = $this->aiUser(['sales']);
        $unit = $this->unit(['name' => 'Tool Approval Unit']);
        $context = $this->createContext($manager, $unit, ['lane' => 'sales', 'role_code' => 'customer']);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        $entitiesBefore = Entity::query()->count();
        $run = $this->createRun(
            $actor,
            $definition->code,
            $definition->version,
            $unit->id,
            $context['id'],
            'synthetic-tool-approval',
        );

        $this->execute($run);
        $run = $run->fresh();

        $this->assertSame('requires_action', $run->status->value);
        $this->assertDatabaseHas('ai_tool_calls', [
            'ai_agent_run_id' => $run->id,
            'tool_code' => 'units.get_sanitized_dossier_profile',
            'authorization_decision' => 'pending_local_authorization',
            'side_effect_class' => 'read_only',
            'status' => 'requires_action',
        ]);
        $this->assertSame($entitiesBefore, Entity::query()->count());
    }

    private function createRun(
        $actor,
        string $definitionCode,
        string $definitionVersion,
        int $unitId,
        int $contextId,
        string $idempotencyKey,
    ): AiAgentRun {
        $id = $this->actingAs($actor)->postJson('/api/ai-sales/runs', [
            'definition_code' => $definitionCode,
            'definition_version' => $definitionVersion,
            'unit_id' => $unitId,
            'unit_business_context_id' => $contextId,
            'idempotency_key' => $idempotencyKey,
        ])->assertCreated()->json('data.id');

        return AiAgentRun::query()->where('public_id', $id)->firstOrFail();
    }

    private function execute(AiAgentRun $run): void
    {
        (new ExecuteAiAgentRunJob($run->id))->handle(
            app(PrepareAiAgentRun::class),
            app(ExecuteAiAgentRunStep::class),
        );
    }
}
