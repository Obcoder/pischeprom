<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('safe_name', 160);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('originating_good_id')->nullable()->constrained('goods')->restrictOnDelete();
            $table->string('purpose', 32)->default('buyer_discovery');
            $table->string('lane', 24)->default('sales');
            $table->string('role_code', 32)->default('prospective_customer');
            $table->string('status', 32)->default('draft');
            $table->string('automation_mode', 32)->default('manual');
            $table->string('safe_objective', 512);
            $table->json('criteria_snapshot')->nullable();
            $table->char('product_scope_hash', 64);
            $table->char('criteria_geography_hash', 64);
            $table->string('workflow_code', 96)->default('buyer_acquisition_campaign.v1');
            $table->string('workflow_version', 32)->default('1');
            $table->char('workflow_hash', 64);
            $table->string('policy_version', 64)->default('stage14-v1');
            $table->char('policy_hash', 64);
            $table->char('disclosure_policy_hash', 64);
            $table->string('schedule_cadence', 24)->default('manual');
            $table->string('schedule_timezone', 64)->default('Europe/Moscow');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedSmallInteger('max_active_runs')->default(1);
            $table->unsignedSmallInteger('max_runs_per_day')->default(0);
            $table->unsignedSmallInteger('max_runs_per_month')->default(0);
            $table->unsignedSmallInteger('max_search_requests_per_run')->default(0);
            $table->unsignedInteger('max_search_requests_per_day')->default(0);
            $table->unsignedInteger('max_search_requests_per_month')->default(0);
            $table->unsignedSmallInteger('max_research_pages_per_run')->default(0);
            $table->unsignedSmallInteger('max_candidates_per_run')->default(0);
            $table->unsignedSmallInteger('max_units_per_run')->default(0);
            $table->unsignedInteger('max_units_per_day')->default(0);
            $table->unsignedInteger('max_units_per_month')->default(0);
            $table->unsignedSmallInteger('max_drafts_per_run')->default(0);
            $table->unsignedInteger('max_drafts_per_day')->default(0);
            $table->unsignedInteger('max_drafts_per_month')->default(0);
            $table->unsignedInteger('max_requests_per_run')->default(0);
            $table->unsignedInteger('max_requests_per_day')->default(0);
            $table->unsignedInteger('max_requests_per_month')->default(0);
            $table->unsignedInteger('max_tokens_per_run')->default(0);
            $table->unsignedInteger('max_tokens_per_day')->default(0);
            $table->unsignedBigInteger('max_tokens_per_month')->default(0);
            $table->decimal('max_cost_rub_per_run', 14, 4)->default(0);
            $table->decimal('max_cost_rub_per_day', 14, 4)->default(0);
            $table->decimal('max_cost_rub_per_month', 14, 4)->default(0);
            $table->string('auto_unit_policy_code', 96)->default('autonomous_unit_creation.v1');
            $table->string('auto_unit_policy_version', 32)->default('1');
            $table->boolean('auto_unit_approved')->default(false);
            $table->string('auto_draft_policy_code', 96)->default('autonomous_outreach_draft.v1');
            $table->string('auto_draft_policy_version', 32)->default('1');
            $table->boolean('auto_draft_approved')->default(false);
            $table->char('approval_snapshot_hash', 64)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('last_block_code', 96)->nullable();
            $table->string('safe_status_summary', 512)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['status', 'next_run_at'], 'ai_campaign_due_idx');
            $table->index(['owner_user_id', 'status'], 'ai_campaign_owner_idx');
            $table->index(['reviewer_user_id', 'status'], 'ai_campaign_reviewer_idx');
            $table->index(['purpose', 'lane', 'role_code'], 'ai_campaign_context_idx');
        });

        Schema::create('ai_sales_campaign_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_sales_campaign_id')->constrained('ai_sales_campaigns')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('role', 16);
            $table->string('source_origin', 32)->default('human_review');
            $table->timestamps();

            $table->unique(['ai_sales_campaign_id', 'product_id'], 'ai_campaign_product_unique');
            $table->index(['product_id', 'role'], 'ai_campaign_product_lookup_idx');
        });

        Schema::create('ai_sales_campaign_run_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_sales_campaign_id')->constrained('ai_sales_campaigns')->restrictOnDelete();
            $table->foreignId('ai_agent_run_id')->constrained('ai_agent_runs')->restrictOnDelete();
            $table->foreignId('prospecting_search_job_id')->nullable()->constrained('prospecting_search_jobs')->nullOnDelete();
            $table->char('approval_snapshot_hash', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamps();

            $table->unique('ai_agent_run_id', 'ai_campaign_run_unique');
            $table->index(['ai_sales_campaign_id', 'created_at'], 'ai_campaign_run_lookup_idx');
            $table->index('prospecting_search_job_id', 'ai_campaign_job_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sales_campaign_run_links');
        Schema::dropIfExists('ai_sales_campaign_products');
        Schema::dropIfExists('ai_sales_campaigns');
    }
};
