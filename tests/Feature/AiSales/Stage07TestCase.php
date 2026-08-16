<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Runs\PrepareAiAgentRun;
use App\Domain\AiSales\Workflows\AiWorkflowExecutionContext;
use App\Models\AiAgentRun;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

abstract class Stage07TestCase extends Stage04TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config()->set([
            'ai-sales.tools.enabled' => true,
            'ai-sales.workflows.enabled' => true,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
        ]);
    }

    protected function tearDown(): void
    {
        try {
            Http::assertNothingSent();
        } finally {
            parent::tearDown();
        }
    }

    protected function workflowUser(array $lanes = ['sales'], array $extra = []): User
    {
        return $this->aiUser($lanes, [
            'ai_sales.tools.view',
            'ai_sales.tools.execute',
            'ai_sales.workflows.execute',
            ...$extra,
        ]);
    }

    /** @return array{run: AiAgentRun, actor: User} */
    protected function preparedSyntheticRun(
        ?User $actor = null,
        string $lane = 'sales',
        string $role = 'customer',
        string $idempotency = 'stage07-prepared-run',
    ): array {
        $manager = $this->manager();
        $actor ??= $this->workflowUser([$lane]);
        $unit = $this->unit(['name' => 'Stage 07 synthetic binding']);
        $context = $this->createContext($manager, $unit, [
            'lane' => $lane,
            'role_code' => $role,
        ]);
        $definition = $this->enableDefinition('unit_public_research_synthetic');
        $publicId = $this->actingAs($actor)->postJson('/api/ai-sales/runs', [
            'definition_code' => $definition->code,
            'definition_version' => $definition->version,
            'unit_id' => $unit->id,
            'unit_business_context_id' => $context['id'],
            'idempotency_key' => $idempotency,
        ])->assertCreated()->json('data.id');
        $run = AiAgentRun::query()->where('public_id', $publicId)->firstOrFail();
        $run = app(PrepareAiAgentRun::class)->handle($run);
        $this->assertSame('ready', $run->status->value);

        return ['run' => $run->fresh()->load('steps'), 'actor' => $actor];
    }

    protected function workflowContext(AiAgentRun $run, string $key = 'stage07-workflow'): AiWorkflowExecutionContext
    {
        $step = $run->steps->first();

        return new AiWorkflowExecutionContext(
            $run->id,
            $step->id,
            $run->initiator_user_id,
            $run->lock_version,
            $key,
        );
    }
}
