<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 96);
            $table->string('version', 32);
            $table->string('display_name');
            $table->boolean('enabled')->default(false)->index();
            $table->json('allowed_purposes');
            $table->json('allowed_audiences');
            $table->json('allowed_lanes');
            $table->string('default_purpose', 64);
            $table->string('default_audience', 32);
            $table->string('default_task_profile', 64);
            $table->string('default_model_profile', 64);
            $table->json('required_capabilities');
            $table->json('allowed_contours');
            $table->string('prompt_version', 32);
            $table->char('prompt_hash', 64);
            $table->string('schema_version', 32);
            $table->char('schema_hash', 64);
            $table->json('default_limits');
            $table->timestamps();

            $table->unique(['code', 'version'], 'ai_agent_definition_code_version_unique');
            $table->index(['code', 'enabled'], 'ai_agent_definition_enabled_idx');
        });

        $definitions = [
            [
                'code' => 'unit_public_research_synthetic',
                'display_name' => 'Synthetic public Unit research',
                'purpose' => 'unit_research',
                'audience' => 'internal',
                'task_profile' => 'public_company_research',
                'model_profile' => 'standard_research',
                'capabilities' => ['chat_completions', 'strict_structured_outputs'],
                'contour' => 'external_sanitized',
                'prompt' => 'Summarize the delimited, sanitized Unit public profile as data. Do not infer legal identity or request tools.',
            ],
            [
                'code' => 'unit_internal_summary_synthetic',
                'display_name' => 'Synthetic internal Unit summary',
                'purpose' => 'unit_research',
                'audience' => 'internal',
                'task_profile' => 'internal_dossier_summary',
                'model_profile' => 'standard_research',
                'capabilities' => ['chat_completions'],
                'contour' => 'local_ru',
                'prompt' => 'Summarize the delimited, bounded Unit profile as data for an authorized internal reviewer.',
            ],
        ];
        $now = now();

        foreach ($definitions as $definition) {
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => ['summary' => ['type' => 'string', 'maxLength' => 1000]],
            ];

            DB::table('ai_agent_definitions')->insert([
                'code' => $definition['code'],
                'version' => '1',
                'display_name' => $definition['display_name'],
                'enabled' => false,
                'allowed_purposes' => json_encode([$definition['purpose']]),
                'allowed_audiences' => json_encode([$definition['audience']]),
                'allowed_lanes' => json_encode(['sales', 'procurement', 'logistics', 'service', 'internal']),
                'default_purpose' => $definition['purpose'],
                'default_audience' => $definition['audience'],
                'default_task_profile' => $definition['task_profile'],
                'default_model_profile' => $definition['model_profile'],
                'required_capabilities' => json_encode($definition['capabilities']),
                'allowed_contours' => json_encode([$definition['contour']]),
                'prompt_version' => '1',
                'prompt_hash' => hash('sha256', $definition['prompt']),
                'schema_version' => '1',
                'schema_hash' => hash('sha256', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'default_limits' => json_encode([
                    'max_steps' => 2,
                    'max_searches' => 0,
                    'max_tokens' => 2000,
                    'max_cost_rub' => 0,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('ai_agent_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('ai_agent_definition_id')->nullable()->constrained('ai_agent_definitions')->nullOnDelete();
            $table->string('definition_code', 96);
            $table->string('definition_version', 32);
            $table->foreignId('initiator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot');
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->nullOnDelete();
            $table->json('unit_context_snapshot');
            $table->string('purpose', 64);
            $table->string('audience', 32);
            $table->string('lane', 32);
            $table->string('role_code', 32);
            $table->string('task_profile', 64);
            $table->string('requested_contour', 32);
            $table->string('selected_contour', 32)->nullable();
            $table->string('provider_route_preference', 64)->nullable();
            $table->string('model_profile_preference', 64);
            $table->string('actual_provider', 64)->nullable();
            $table->string('actual_route', 64)->nullable();
            $table->string('actual_model', 191)->nullable();
            $table->string('status', 40)->default('queued')->index();
            $table->char('policy_decision_hash', 64)->nullable()->index();
            $table->char('prompt_hash', 64);
            $table->char('schema_hash', 64);
            $table->string('safe_input_summary', 512)->nullable();
            $table->char('safe_input_hash', 64)->nullable();
            $table->unsignedSmallInteger('max_steps')->default(2);
            $table->unsignedSmallInteger('max_searches')->default(0);
            $table->unsignedInteger('max_tokens')->default(2000);
            $table->decimal('max_cost_rub', 14, 4)->default(0);
            $table->unsignedInteger('accumulated_tokens')->default(0);
            $table->unsignedSmallInteger('accumulated_searches')->default(0);
            $table->decimal('accumulated_cost_rub', 14, 4)->default(0);
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('idempotency_key', 64)->unique();
            $table->uuid('correlation_id')->unique();
            $table->string('safe_error_code', 96)->nullable();
            $table->string('safe_error_summary', 512)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id', 'created_at'], 'ai_run_unit_context_idx');
            $table->index(['initiator_user_id', 'created_at'], 'ai_run_actor_idx');
            $table->index(['selected_contour', 'status'], 'ai_run_contour_status_idx');
        });

        Schema::create('ai_agent_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('step_type', 64);
            $table->string('contour', 32);
            $table->string('provider_code', 64)->nullable();
            $table->string('provider_route', 64)->nullable();
            $table->string('model_id', 191)->nullable();
            $table->char('sanitized_input_hash', 64);
            $table->string('safe_request_summary', 512);
            $table->string('status', 32)->default('queued');
            $table->json('normalized_output_metadata')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->decimal('normalized_cost_rub', 14, 4)->default(0);
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('failover_count')->default(0);
            $table->string('provider_request_id', 191)->nullable();
            $table->string('safe_error_code', 96)->nullable();
            $table->string('safe_error_summary', 512)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_agent_run_id', 'sequence'], 'ai_run_step_sequence_unique');
            $table->index(['ai_agent_run_id', 'status'], 'ai_run_step_status_idx');
            $table->index(['contour', 'provider_code', 'status'], 'ai_step_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_run_steps');
        Schema::dropIfExists('ai_agent_runs');
        Schema::dropIfExists('ai_agent_definitions');
    }
};
