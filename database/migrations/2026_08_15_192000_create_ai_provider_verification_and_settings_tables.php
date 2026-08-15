<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64);
            $table->string('provider_route', 64);
            $table->string('model_id', 191);
            $table->string('contour', 32);
            $table->string('capability', 64);
            $table->string('status', 32)->default('unknown');
            $table->unsignedInteger('max_context_tokens')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->string('evidence_reference', 512)->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('probe_version', 64)->nullable();
            $table->timestamps();

            $table->unique(['provider_code', 'provider_route', 'model_id', 'capability'], 'ai_provider_capability_unique');
            $table->index(['contour', 'status', 'expires_at'], 'ai_provider_capability_state_idx');
        });

        Schema::create('ai_model_residency_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64);
            $table->string('provider_route', 64);
            $table->string('model_id', 191);
            $table->string('declared_contour', 32);
            $table->string('declared_country', 2);
            $table->string('evidence_reference', 512);
            $table->char('evidence_hash', 64);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('probe_version', 64)->nullable();
            $table->string('notes', 512)->nullable();
            $table->timestamps();

            $table->unique(['provider_code', 'provider_route', 'model_id'], 'ai_model_residency_route_model_unique');
            $table->index(['declared_contour', 'status', 'expires_at'], 'ai_model_residency_state_idx');
        });

        Schema::create('ai_control_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 96)->unique();
            $table->boolean('boolean_value')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('ai_control_settings')->insert([
            ['key' => 'kill_switch.global', 'boolean_value' => false, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'kill_switch.local_ru', 'boolean_value' => false, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'kill_switch.external_sanitized', 'boolean_value' => false, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $capabilities = [
            'fake-local-ru-v1' => ['chat_completions', 'strict_structured_outputs', 'function_calling', 'usage_reporting', 'request_id'],
            'fake-external-sanitized-v1' => ['chat_completions', 'strict_structured_outputs', 'function_calling', 'reasoning', 'usage_reporting', 'request_id', 'store_false'],
        ];

        foreach ($capabilities as $modelId => $modelCapabilities) {
            $contour = $modelId === 'fake-local-ru-v1' ? 'local_ru' : 'external_sanitized';

            foreach ($modelCapabilities as $capability) {
                DB::table('ai_provider_capabilities')->insert([
                    'provider_code' => 'fake',
                    'provider_route' => $contour,
                    'model_id' => $modelId,
                    'contour' => $contour,
                    'capability' => $capability,
                    'status' => 'synthetic_tested',
                    'max_context_tokens' => 16_000,
                    'max_output_tokens' => 4_000,
                    'evidence_reference' => 'code-owned:stage04-fake-contract-tests',
                    'evidence_hash' => hash('sha256', 'stage04-fake:'.$modelId.':'.$capability),
                    'verified_by' => null,
                    'verified_at' => $now,
                    'expires_at' => null,
                    'probe_version' => 'stage04-fake-v1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_control_settings');
        Schema::dropIfExists('ai_model_residency_verifications');
        Schema::dropIfExists('ai_provider_capabilities');
    }
};
