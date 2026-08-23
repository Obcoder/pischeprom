<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('ai_agent_run_step_id')->constrained('ai_agent_run_steps')->restrictOnDelete();
            $table->string('call_id', 128);
            $table->string('tool_code', 96);
            $table->string('tool_version', 32);
            $table->string('contour', 32);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->nullOnDelete();
            $table->json('context_snapshot');
            $table->char('arguments_hash', 64);
            $table->char('output_hash', 64)->nullable();
            $table->string('redacted_arguments_summary', 512);
            $table->string('redacted_output_summary', 512)->nullable();
            $table->string('authorization_decision', 32);
            $table->char('policy_decision_hash', 64);
            $table->char('idempotency_key', 64);
            $table->string('side_effect_class', 32)->default('read_only');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 32);
            $table->string('safe_error_code', 96)->nullable();
            $table->string('safe_error_summary', 512)->nullable();
            $table->timestamps();

            $table->unique(['ai_agent_run_id', 'call_id'], 'ai_tool_call_run_call_unique');
            $table->unique('idempotency_key', 'ai_tool_call_idempotency_unique');
            $table->index(['tool_code', 'status'], 'ai_tool_call_status_idx');
        });

        Schema::create('ai_policy_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('ai_agent_run_step_id')->nullable()->constrained('ai_agent_run_steps')->restrictOnDelete();
            $table->foreignId('ai_tool_call_id')->nullable()->constrained('ai_tool_calls')->restrictOnDelete();
            $table->string('disclosure_policy_version', 64);
            $table->string('contour_policy_version', 64);
            $table->json('classification_snapshot');
            $table->json('visibility_snapshot');
            $table->string('decision', 32);
            $table->string('contour', 32);
            $table->string('reason_code', 96);
            $table->unsignedInteger('redaction_count')->default(0);
            $table->boolean('requires_human_review')->default(false);
            $table->char('decision_hash', 64)->index();
            $table->timestamps();

            $table->index(['ai_agent_run_id', 'created_at'], 'ai_policy_run_timeline_idx');
            $table->index(['decision', 'contour'], 'ai_policy_decision_contour_idx');
        });

        Schema::create('ai_data_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('ai_tool_call_id')->nullable()->constrained('ai_tool_calls')->restrictOnDelete();
            $table->string('dto_type', 191);
            $table->string('source_type', 96);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('contour', 32);
            $table->json('classification_summary');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('byte_count')->default(0);
            $table->string('decision', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ai_agent_run_id', 'created_at'], 'ai_data_access_run_idx');
            $table->index(['source_type', 'source_id'], 'ai_data_access_source_idx');
        });

        Schema::create('ai_redaction_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('ai_agent_run_step_id')->nullable()->constrained('ai_agent_run_steps')->restrictOnDelete();
            $table->string('detector', 96);
            $table->string('rule_code', 96);
            $table->string('finding_type', 64);
            $table->string('action', 32);
            $table->char('path_hash', 64);
            $table->unsignedSmallInteger('occurrences')->default(1);
            $table->timestamps();

            $table->index(['ai_agent_run_id', 'finding_type'], 'ai_redaction_run_type_idx');
        });

        // Stage 01 already introduced the shared, append-only ai_usage_records ledger.
        // Extend it in place so price-list usage and AI-sales usage remain compatible.
        Schema::table('ai_usage_records', function (Blueprint $table): void {
            $table->foreignId('ai_agent_run_id')->nullable()->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('ai_agent_run_step_id')->nullable()->constrained('ai_agent_run_steps')->restrictOnDelete();
            $table->string('contour', 32)->nullable();
            $table->string('provider_route', 64)->nullable();
            $table->string('capability', 64)->nullable();
            $table->string('endpoint', 64)->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('cached_tokens')->nullable();
            $table->unsignedSmallInteger('search_count')->default(0);
            $table->unsignedSmallInteger('tool_call_count')->default(0);
            $table->decimal('normalized_rub_amount', 14, 4)->default(0);

            $table->index(['ai_agent_run_id', 'created_at'], 'ai_usage_run_idx');
            $table->index(['contour', 'provider', 'created_at'], 'ai_usage_contour_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_records', function (Blueprint $table): void {
            $table->dropIndex('ai_usage_contour_provider_idx');
            $table->dropIndex('ai_usage_run_idx');
            $table->dropForeign(['ai_agent_run_step_id']);
            $table->dropForeign(['ai_agent_run_id']);
            $table->dropColumn([
                'ai_agent_run_id',
                'ai_agent_run_step_id',
                'contour',
                'provider_route',
                'capability',
                'endpoint',
                'reasoning_tokens',
                'cached_tokens',
                'search_count',
                'tool_call_count',
                'normalized_rub_amount',
            ]);
        });
        Schema::dropIfExists('ai_redaction_events');
        Schema::dropIfExists('ai_data_access_logs');
        Schema::dropIfExists('ai_policy_decisions');
        Schema::dropIfExists('ai_tool_calls');
    }
};
