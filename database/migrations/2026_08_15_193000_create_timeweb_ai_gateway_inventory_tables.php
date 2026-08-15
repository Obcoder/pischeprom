<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_models', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64);
            $table->string('provider_route', 64);
            $table->string('model_id', 191);
            $table->string('display_label', 191)->nullable();
            $table->string('endpoint_profile', 32)->default('unsupported');
            $table->boolean('active_in_inventory')->default(false);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->json('safe_metadata')->nullable();
            $table->string('source_reference', 512);
            $table->char('metadata_hash', 64);
            $table->string('created_by_reference', 128)->nullable();
            $table->string('updated_by_reference', 128)->nullable();
            $table->timestamps();

            $table->unique(['provider_code', 'provider_route', 'model_id'], 'ai_provider_model_route_unique');
            $table->index(['provider_code', 'provider_route', 'active_in_inventory'], 'ai_provider_model_active_idx');
            $table->index(['endpoint_profile', 'active_in_inventory'], 'ai_provider_model_endpoint_idx');
        });

        Schema::create('ai_provider_pricing_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64);
            $table->string('provider_route', 64);
            $table->string('model_id', 191);
            $table->string('version', 64);
            $table->char('currency', 3)->default('RUB');
            $table->decimal('input_per_million', 16, 6);
            $table->decimal('output_per_million', 16, 6);
            $table->decimal('reasoning_per_million', 16, 6)->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source_reference', 512);
            $table->char('source_hash', 64);
            $table->string('recorded_by_reference', 128);
            $table->timestamps();

            $table->unique(['provider_code', 'provider_route', 'model_id', 'version'], 'ai_provider_pricing_version_unique');
            $table->index(['provider_code', 'provider_route', 'model_id', 'effective_at'], 'ai_provider_pricing_effective_idx');
        });

        Schema::table('ai_provider_capabilities', function (Blueprint $table): void {
            $table->string('support_state', 24)->default('unknown')->after('status');
            $table->string('evidence_source', 32)->nullable()->after('evidence_hash');
            $table->string('safe_request_id', 191)->nullable()->after('evidence_source');
            $table->string('adapter_version', 64)->nullable()->after('safe_request_id');
            $table->string('policy_version', 64)->nullable()->after('adapter_version');
            $table->string('schema_version', 64)->nullable()->after('policy_version');
            $table->char('result_hash', 64)->nullable()->after('schema_version');
            $table->string('operator_reference', 128)->nullable()->after('result_hash');
            $table->index(['provider_code', 'provider_route', 'model_id', 'support_state'], 'ai_provider_capability_support_idx');
        });

        // Stage 04 rows represented supported fake capabilities before support_state existed.
        DB::table('ai_provider_capabilities')
            ->where('provider_code', 'fake')
            ->update(['support_state' => 'supported']);
    }

    public function down(): void
    {
        Schema::table('ai_provider_capabilities', function (Blueprint $table): void {
            $table->dropIndex('ai_provider_capability_support_idx');
            $table->dropColumn([
                'support_state',
                'evidence_source',
                'safe_request_id',
                'adapter_version',
                'policy_version',
                'schema_version',
                'result_hash',
                'operator_reference',
            ]);
        });

        Schema::dropIfExists('ai_provider_pricing_snapshots');
        Schema::dropIfExists('ai_provider_models');
    }
};
