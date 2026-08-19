<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospecting_search_queries', function (Blueprint $table): void {
            $table->string('template_code', 64)->nullable()->after('sequence');
            $table->string('template_version', 32)->nullable()->after('template_code');
            $table->char('template_hash', 64)->nullable()->after('template_version');
            $table->char('product_scope_hash', 64)->nullable()->after('template_hash');
            $table->char('plan_hash', 64)->nullable()->after('product_scope_hash');
            $table->string('plan_status', 24)->nullable()->after('plan_hash');
            $table->foreignId('plan_approved_by')->nullable()->after('plan_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('plan_approved_at')->nullable()->after('plan_approved_by');

            $table->index(
                ['prospecting_search_job_id', 'plan_status'],
                'prospecting_query_plan_status_idx',
            );
        });

        Schema::create('prospecting_search_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('prospecting_search_job_id')->constrained('prospecting_search_jobs')->restrictOnDelete();
            $table->foreignId('prospecting_search_query_id')
                ->constrained('prospecting_search_queries', indexName: 'ps_executions_query_fk')->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('profile_code', 64);
            $table->string('provider_code', 64);
            $table->char('request_hash', 64);
            $table->char('plan_hash', 64);
            $table->string('status', 24)->default('queued');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedSmallInteger('request_count')->default(0);
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('blocked_result_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_request_id', 128)->nullable();
            $table->string('error_category', 64)->nullable();
            $table->string('error_code', 96)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['prospecting_search_query_id', 'request_hash'],
                'prospecting_search_execution_idempotency_unique',
            );
            $table->index(
                ['prospecting_search_job_id', 'status', 'created_at'],
                'prospecting_search_execution_status_idx',
            );
        });

        Schema::create('prospecting_search_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('prospecting_search_execution_id')
                ->constrained('prospecting_search_executions', indexName: 'ps_results_execution_fk')->restrictOnDelete();
            $table->foreignId('prospecting_search_job_id')->constrained('prospecting_search_jobs')->restrictOnDelete();
            $table->foreignId('prospecting_search_query_id')->constrained('prospecting_search_queries')->restrictOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('result_type', 24)->default('organic');
            $table->string('title', 512)->nullable();
            $table->string('snippet', 2000)->nullable();
            $table->text('url');
            $table->text('canonical_url');
            $table->char('url_hash', 64);
            $table->string('registrable_domain', 253);
            $table->char('domain_hash', 64);
            $table->char('result_hash', 64);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('prospecting_search_results')->nullOnDelete();
            $table->foreignId('prospecting_candidate_id')->nullable()->constrained('prospecting_candidates')->nullOnDelete();
            $table->string('trust_level', 24)->default('untrusted');
            $table->string('instruction_authority', 24)->default('none');
            $table->string('fetch_status', 24)->default('not_requested');
            $table->string('research_status', 24)->default('not_requested');
            $table->timestamps();

            $table->unique(
                ['prospecting_search_execution_id', 'result_hash'],
                'prospecting_search_result_execution_hash_unique',
            );
            $table->index(
                ['prospecting_search_job_id', 'domain_hash', 'rank'],
                'prospecting_search_result_domain_idx',
            );
            $table->index(
                ['prospecting_search_job_id', 'prospecting_candidate_id'],
                'prospecting_search_result_candidate_idx',
            );
        });

        Schema::create('prospecting_search_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_execution_id')->unique('ps_usage_execution_unique')
                ->constrained('prospecting_search_executions', indexName: 'ps_usage_execution_fk')->restrictOnDelete();
            $table->string('provider_code', 64);
            $table->string('profile_code', 64);
            $table->unsignedSmallInteger('request_count')->default(0);
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_rub', 12, 4)->default(0);
            $table->string('safe_request_id', 128)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('prospecting_public_fetches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_result_id')->unique()
                ->constrained('prospecting_search_results')->restrictOnDelete();
            $table->string('status', 24);
            $table->text('final_url')->nullable();
            $table->char('final_url_hash', 64)->nullable();
            $table->string('registrable_domain', 253)->nullable();
            $table->string('content_type', 96)->nullable();
            $table->unsignedInteger('byte_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('page_title', 512)->nullable();
            $table->string('meta_description', 1000)->nullable();
            $table->json('headings')->nullable();
            $table->longText('text_excerpt')->nullable();
            $table->json('same_domain_links')->nullable();
            $table->longText('protected_channels')->nullable();
            $table->unsignedSmallInteger('channel_count')->default(0);
            $table->char('content_hash', 64)->nullable();
            $table->string('trust_level', 24)->default('untrusted');
            $table->string('instruction_authority', 24)->default('none');
            $table->string('robots_status', 24)->nullable();
            $table->string('error_category', 64)->nullable();
            $table->string('error_code', 96)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prospecting_public_research_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_result_id')->unique('ps_research_result_unique')
                ->constrained('prospecting_search_results', indexName: 'ps_research_result_fk')->restrictOnDelete();
            $table->string('workflow_code', 96);
            $table->string('workflow_version', 32);
            $table->char('workflow_hash', 64);
            $table->string('status', 24);
            $table->char('input_hash', 64);
            $table->char('output_hash', 64)->nullable();
            $table->boolean('schema_valid')->default(false);
            $table->string('safe_summary', 1000)->nullable();
            $table->json('activity_mentions')->nullable();
            $table->json('location_hints')->nullable();
            $table->json('product_mentions')->nullable();
            $table->string('provider_code', 64)->default('fake');
            $table->string('model_id', 128)->nullable();
            $table->string('safe_request_id', 128)->nullable();
            $table->string('error_category', 64)->nullable();
            $table->string('error_code', 96)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_public_research_records');
        Schema::dropIfExists('prospecting_public_fetches');
        Schema::dropIfExists('prospecting_search_usage_records');
        Schema::dropIfExists('prospecting_search_results');
        Schema::dropIfExists('prospecting_search_executions');

        Schema::table('prospecting_search_queries', function (Blueprint $table): void {
            $table->dropIndex('prospecting_query_plan_status_idx');
            $table->dropConstrainedForeignId('plan_approved_by');
            $table->dropColumn([
                'template_code', 'template_version', 'template_hash', 'product_scope_hash',
                'plan_hash', 'plan_status', 'plan_approved_at',
            ]);
        });
    }
};
