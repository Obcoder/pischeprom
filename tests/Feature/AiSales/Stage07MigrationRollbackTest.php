<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Stage07MigrationRollbackTest extends Stage07TestCase
{
    public function test_stage07_migration_is_additive_reversible_and_preserves_existing_tool_calls(): void
    {
        ['run' => $run] = $this->preparedSyntheticRun(idempotency: 'stage07-migration-run');
        $step = $run->steps->first();
        $historicalId = DB::table('ai_tool_calls')->insertGetId([
            'ai_agent_run_id' => $run->id,
            'ai_agent_run_step_id' => $step->id,
            'call_id' => 'historical-stage04-call',
            'tool_code' => 'historical.synthetic.tool',
            'tool_version' => '1',
            'contour' => 'external_sanitized',
            'unit_id' => $run->unit_id,
            'unit_business_context_id' => $run->unit_business_context_id,
            'context_snapshot' => json_encode(['historical' => true]),
            'arguments_hash' => hash('sha256', 'historical-arguments'),
            'redacted_arguments_summary' => 'Historical safe summary.',
            'authorization_decision' => 'pending_local_authorization',
            'policy_decision_hash' => $run->policy_decision_hash,
            'idempotency_key' => hash('sha256', 'historical-stage04-call'),
            'side_effect_class' => 'read_only',
            'status' => 'requires_action',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_08_16_194000_extend_ai_tool_calls_for_stage07_workflows.php');

        try {
            $migration->down();

            $this->assertTrue(Schema::hasTable('ai_tool_calls'));
            $this->assertTrue(Schema::hasColumn('ai_tool_calls', 'arguments_hash'));
            $this->assertFalse(Schema::hasColumn('ai_tool_calls', 'workflow_hash'));
            $this->assertFalse(Schema::hasColumn('ai_tool_calls', 'row_count'));
            $this->assertSame(
                'historical.synthetic.tool',
                DB::table('ai_tool_calls')->where('id', $historicalId)->value('tool_code'),
            );
            $this->assertTrue(Schema::hasTable('ai_agent_runs'));
            $this->assertTrue(Schema::hasTable('unit_business_contexts'));
        } finally {
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumn('ai_tool_calls', 'workflow_hash'));
        $this->assertTrue(Schema::hasColumn('ai_tool_calls', 'row_count'));
        $this->assertSame(
            'historical.synthetic.tool',
            DB::table('ai_tool_calls')->where('id', $historicalId)->value('tool_code'),
        );
    }
}
