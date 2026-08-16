<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_search_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 32);
            $table->string('lane', 24);
            $table->string('default_role_code', 32);
            $table->foreignId('primary_good_id')->nullable()->constrained('goods')->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('locale', 12)->default('ru-RU');
            $table->unsignedSmallInteger('max_queries')->default(10);
            $table->unsignedSmallInteger('max_candidates')->default(100);
            $table->unsignedSmallInteger('max_results_per_query')->default(20);
            $table->unsignedInteger('max_rows')->default(500);
            $table->unsignedInteger('max_bytes')->default(1048576);
            $table->unsignedSmallInteger('max_searches')->default(0);
            $table->decimal('max_cost_rub', 12, 4)->default(0);
            $table->string('safe_objective', 512);
            $table->json('criteria_snapshot')->nullable();
            $table->string('policy_version', 64)->default('stage08-v1');
            $table->string('workflow_version', 64)->default('stage08-no-execution');
            $table->char('schema_hash', 64);
            $table->string('status', 32)->default('draft');
            $table->boolean('auto_create_unit')->default(false);
            $table->string('retention_profile', 64)->default('prospecting-transient-v1');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('ai_agent_run_id')->nullable()->constrained('ai_agent_runs')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['lane', 'status', 'created_at'], 'prospecting_job_lane_status_idx');
            $table->index(['owner_user_id', 'status'], 'prospecting_job_owner_status_idx');
        });

        Schema::create('prospecting_search_job_goods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_job_id')->constrained('prospecting_search_jobs')->restrictOnDelete();
            $table->foreignId('good_id')->constrained('goods')->restrictOnDelete();
            $table->string('role', 16)->default('additional');
            $table->timestamps();

            $table->unique(['prospecting_search_job_id', 'good_id'], 'prospecting_job_good_unique');
            $table->index(['good_id', 'role'], 'prospecting_job_good_lookup_idx');
        });

        Schema::create('prospecting_search_queries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_job_id')->constrained('prospecting_search_jobs')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->char('query_hash', 64);
            $table->string('safe_display_query', 512);
            $table->string('language', 12)->default('ru');
            $table->string('geography', 255)->nullable();
            $table->string('industry_intent', 255)->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('candidate_count')->default(0);
            $table->string('search_provider_reference', 255)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['prospecting_search_job_id', 'sequence'], 'prospecting_query_sequence_unique');
            $table->unique(['prospecting_search_job_id', 'query_hash'], 'prospecting_query_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_search_queries');
        Schema::dropIfExists('prospecting_search_job_goods');
        Schema::dropIfExists('prospecting_search_jobs');
    }
};
