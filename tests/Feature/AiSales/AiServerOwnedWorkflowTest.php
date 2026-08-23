<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Enums\FakeAiProviderScenario;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Workflows\AiWorkflowExecutionContext;
use App\Domain\AiSales\Workflows\AiWorkflowExecutor;
use App\Infrastructure\AiSales\Providers\FakeExternalSanitizedAiProvider;
use App\Infrastructure\AiSales\Providers\FakeLocalRuAiProvider;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AiServerOwnedWorkflowTest extends Stage07TestCase
{
    public function test_synthetic_server_owned_workflow_uses_fixed_tool_fake_provider_and_safe_audit_only(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun();
        $entitiesBefore = Entity::query()->count();
        $result = app(AiWorkflowExecutor::class)->execute($this->workflowContext($run));
        $run = $run->fresh()->load('steps');

        $this->assertSame('completed', $result->status);
        $this->assertSame('synthetic.good_context_classification.v1', $result->workflowCode);
        $this->assertSame(1, $result->toolCallCount);
        $this->assertSame(1, $result->rowCount);
        $this->assertSame('completed', $run->status->value);
        $this->assertSame('fake', $run->actual_provider);
        $this->assertSame('external_sanitized', $run->actual_route);
        $this->assertSame(0, $run->steps->first()->failover_count);
        $this->assertSame(0, $run->steps->first()->retry_count);
        $this->assertSame(0, DB::table('ai_tool_calls')->where('ai_agent_run_id', $run->id)->value('query_count'));
        $this->assertDatabaseHas('ai_tool_calls', [
            'ai_agent_run_id' => $run->id,
            'tool_code' => 'catalog.get_synthetic_good',
            'tool_version' => '1',
            'workflow_code' => 'synthetic.good_context_classification.v1',
            'status' => 'completed',
            'side_effect_class' => 'read_only',
            'authorization_decision' => 'allow',
            'row_count' => 1,
            'query_count' => 0,
        ]);
        $this->assertDatabaseHas('ai_usage_records', [
            'ai_agent_run_id' => $run->id,
            'operation' => 'ai_sales_server_owned_synthetic_workflow',
            'provider' => 'fake',
            'provider_route' => 'external_sanitized',
            'tool_call_count' => 1,
        ]);
        $this->assertSame($entitiesBefore, Entity::query()->count());

        $persisted = json_encode([
            DB::table('ai_tool_calls')->where('ai_agent_run_id', $run->id)->get(),
            DB::table('ai_data_access_logs')->where('ai_agent_run_id', $run->id)->get(),
            DB::table('ai_agent_run_steps')->where('ai_agent_run_id', $run->id)->get(),
            DB::table('ai_usage_records')->where('ai_agent_run_id', $run->id)->get(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('SYN-001', $persisted);
        $this->assertStringNotContainsString('Fictional starch blend', $persisted);
        $this->assertStringNotContainsString('Synthetic fake response.', $persisted);
        $this->assertStringNotContainsString('Classify only the repository-owned', $persisted);
        $this->assertDoesNotMatchRegularExpression('/bearer\s|api[_-]?key\s*[:=]|begin private key/i', $persisted);
    }

    public function test_workflow_replay_is_idempotent_and_duplicate_job_does_not_repeat_provider_or_tool(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-replay-run');
        $context = $this->workflowContext($run, 'stage07-replay');
        $first = app(AiWorkflowExecutor::class)->execute($context);
        $second = app(AiWorkflowExecutor::class)->execute($context);

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($run, 'stage07-different-replay'));
            $this->fail('A different idempotency binding replayed the completed workflow.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('workflow_idempotency_conflict', $violation->errorCode);
        }

        $this->assertDatabaseCount('ai_tool_calls', 1);
        $this->assertSame(1, DB::table('ai_usage_records')
            ->where('ai_agent_run_id', $run->id)
            ->where('operation', 'ai_sales_server_owned_synthetic_workflow')
            ->count());
    }

    public function test_workflow_idor_actor_step_and_stale_lock_rejections_do_not_mutate_victim_records(): void
    {
        ['run' => $victim] = $this->preparedSyntheticRun(idempotency: 'stage07-idor-victim');
        ['run' => $other] = $this->preparedSyntheticRun(idempotency: 'stage07-idor-other');
        $executor = app(AiWorkflowExecutor::class);
        $contexts = [
            new AiWorkflowExecutionContext(
                $victim->id,
                $other->steps->first()->id,
                $victim->initiator_user_id,
                $victim->lock_version,
                'foreign-step',
            ),
            new AiWorkflowExecutionContext(
                $victim->id,
                $victim->steps->first()->id,
                $other->initiator_user_id,
                $victim->lock_version,
                'foreign-actor',
            ),
            new AiWorkflowExecutionContext(
                $victim->id,
                $victim->steps->first()->id,
                $victim->initiator_user_id,
                $victim->lock_version + 1,
                'stale-lock',
            ),
        ];

        foreach ($contexts as $context) {
            try {
                $executor->execute($context);
                $this->fail('An IDOR or stale workflow binding was accepted.');
            } catch (PolicyViolation $violation) {
                $this->assertSame('workflow_execution_binding_mismatch', $violation->errorCode);
            }
        }

        $this->assertSame('ready', $victim->fresh()->status->value);
        $this->assertSame('ready', $other->fresh()->status->value);
        $this->assertSame('ready', $victim->steps->first()->fresh()->status->value);
        $this->assertSame('ready', $other->steps->first()->fresh()->status->value);
        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $victim->id]);
        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $other->id]);
    }

    public function test_actor_permission_and_changed_context_are_rechecked_before_synthetic_handler(): void
    {
        ['run' => $permissionRun, 'actor' => $actor] = $this->preparedSyntheticRun(idempotency: 'stage07-permission-run');
        $actor->revokePermissionTo('ai_sales.tools.execute');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($permissionRun, 'revoked'));
            $this->fail('Revoked tool permission was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('tool_permission_revoked', $violation->errorCode);
        }

        $this->assertSame('blocked_by_policy', $permissionRun->fresh()->status->value);
        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $permissionRun->id]);

        ['run' => $staleRun] = $this->preparedSyntheticRun(idempotency: 'stage07-stale-context-run');
        DB::table('unit_business_contexts')->where('id', $staleRun->unit_business_context_id)->update([
            'lane' => 'procurement',
            'role_code' => 'supplier',
        ]);

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($staleRun, 'stale'));
            $this->fail('Changed Unit context was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('run_reauthorization_failed', $violation->errorCode);
        }

        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $staleRun->id]);
    }

    public function test_default_off_and_cancelled_workflow_have_no_tool_or_provider_side_effects(): void
    {
        ['run' => $disabled] = $this->preparedSyntheticRun(idempotency: 'stage07-disabled-run');
        config()->set('ai-sales.tools.enabled', false);

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($disabled, 'disabled'));
            $this->fail('Disabled tooling executed.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('workflow_feature_guard_blocked', $violation->errorCode);
        }

        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $disabled->id]);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $disabled->id]);

        config()->set('ai-sales.tools.enabled', true);
        ['run' => $cancelled, 'actor' => $actor] = $this->preparedSyntheticRun(idempotency: 'stage07-cancelled-run');
        $this->actingAs($actor)->postJson("/api/ai-sales/runs/{$cancelled->public_id}/cancel")->assertOk();

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($cancelled, 'cancelled'));
            $this->fail('Cancelled workflow executed.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('workflow_execution_binding_mismatch', $violation->errorCode);
        }

        $this->assertDatabaseMissing('ai_tool_calls', ['ai_agent_run_id' => $cancelled->id]);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $cancelled->id]);
    }

    public function test_unexpected_native_tool_call_is_protocol_violation_and_never_mutates_entity(): void
    {
        $registry = new AiProviderRegistry;
        $registry->register(new FakeLocalRuAiProvider);
        $registry->register(new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::FunctionCall));
        $this->app->instance(AiProviderRegistry::class, $registry);
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-native-call-run');
        $entitiesBefore = Entity::query()->count();

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($run, 'native-call'));
            $this->fail('Unexpected provider-native tool call was accepted.');
        } catch (PolicyViolation $violation) {
            $this->assertContains($violation->errorCode, [
                'workflow_unexpected_native_tool_call',
                'workflow_provider_protocol_violation',
            ]);
        }

        $this->assertSame($entitiesBefore, Entity::query()->count());
        $this->assertSame('blocked_by_policy', $run->fresh()->status->value);
        $this->assertSame(1, DB::table('ai_tool_calls')->where('ai_agent_run_id', $run->id)->count());
        $this->assertDatabaseMissing('ai_tool_calls', [
            'ai_agent_run_id' => $run->id,
            'tool_code' => 'units.get_sanitized_dossier_profile',
        ]);
    }

    public function test_provider_unavailable_has_zero_retry_zero_failover_and_no_local_route_call(): void
    {
        $registry = new AiProviderRegistry;
        $registry->register(new FakeLocalRuAiProvider);
        $registry->register(new FakeExternalSanitizedAiProvider(FakeAiProviderScenario::ProviderUnavailable));
        $this->app->instance(AiProviderRegistry::class, $registry);
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-no-fallback-run');

        try {
            app(AiWorkflowExecutor::class)->execute($this->workflowContext($run, 'no-fallback'));
            $this->fail('Unavailable provider workflow completed.');
        } catch (PolicyViolation $violation) {
            $this->assertSame('workflow_provider_unavailable', $violation->errorCode);
        }

        $run = $run->fresh()->load('steps');
        $this->assertSame('provider_unavailable', $run->status->value);
        $this->assertSame('external_sanitized', $run->selected_contour->value);
        $this->assertSame(0, $run->steps->first()->retry_count);
        $this->assertSame(0, $run->steps->first()->failover_count);
        $this->assertDatabaseMissing('ai_usage_records', ['ai_agent_run_id' => $run->id]);
        $this->assertDatabaseMissing('ai_usage_records', [
            'ai_agent_run_id' => $run->id,
            'provider_route' => 'local_ru',
        ]);
    }
}
