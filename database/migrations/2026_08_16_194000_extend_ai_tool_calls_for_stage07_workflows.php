<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')->nullable()->after('unit_business_context_id')->constrained('users')->nullOnDelete();
            $table->foreignId('ai_policy_decision_id')->nullable()->after('actor_user_id')->constrained('ai_policy_decisions')->restrictOnDelete();
            $table->string('workflow_code', 96)->nullable()->after('tool_version');
            $table->string('workflow_version', 32)->nullable()->after('workflow_code');
            $table->char('workflow_hash', 64)->nullable()->after('workflow_version');
            $table->char('tool_schema_hash', 64)->nullable()->after('workflow_hash');
            $table->string('tool_policy_version', 64)->nullable()->after('tool_schema_hash');
            $table->char('safe_input_hash', 64)->nullable()->after('arguments_hash');
            $table->unsignedSmallInteger('row_count')->default(0)->after('side_effect_class');
            $table->unsignedInteger('byte_count')->default(0)->after('row_count');
            $table->unsignedSmallInteger('query_count')->default(0)->after('byte_count');
            $table->unsignedSmallInteger('redaction_count')->default(0)->after('query_count');
            $table->json('budget_reservation')->nullable()->after('redaction_count');
            $table->string('error_category', 64)->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('safe_error_summary');
            $table->timestamp('finished_at')->nullable()->after('started_at');

            $table->index(['workflow_code', 'workflow_version', 'status'], 'ai_tool_call_workflow_status_idx');
            $table->index(['actor_user_id', 'created_at'], 'ai_tool_call_actor_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->dropIndex('ai_tool_call_workflow_status_idx');
            $table->dropIndex('ai_tool_call_actor_timeline_idx');
            $table->dropForeign(['ai_policy_decision_id']);
            $table->dropForeign(['actor_user_id']);
            $table->dropColumn([
                'actor_user_id',
                'ai_policy_decision_id',
                'workflow_code',
                'workflow_version',
                'workflow_hash',
                'tool_schema_hash',
                'tool_policy_version',
                'safe_input_hash',
                'row_count',
                'byte_count',
                'query_count',
                'redaction_count',
                'budget_reservation',
                'error_category',
                'started_at',
                'finished_at',
            ]);
        });
    }
};
