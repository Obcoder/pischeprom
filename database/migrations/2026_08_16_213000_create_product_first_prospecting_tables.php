<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_search_job_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_search_job_id')->constrained('prospecting_search_jobs')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('role', 16);
            $table->string('source_origin', 32)->default('manual_review');
            $table->timestamps();

            $table->unique(['prospecting_search_job_id', 'product_id'], 'prospecting_job_product_unique');
            $table->index(['product_id', 'role'], 'prospecting_job_product_lookup_idx');
        });

        Schema::create('prospecting_candidate_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id')->constrained('prospecting_candidates')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('source', 24);
            $table->string('status', 24)->default('suggested');
            $table->string('safe_rationale', 1000);
            $table->string('evidence_reference', 512)->nullable();
            $table->char('evidence_hash', 64);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'product_id'], 'prospecting_candidate_product_unique');
            $table->index(['product_id', 'status'], 'prospecting_candidate_product_lookup_idx');
        });

        Schema::create('unit_product_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_source_id')->nullable()->constrained('unit_sources')->restrictOnDelete();
            $table->foreignId('prospecting_candidate_product_id')->nullable()->constrained('prospecting_candidate_products')->nullOnDelete();
            $table->string('match_type', 24);
            $table->string('status', 24)->default('suggested');
            $table->string('origin', 16)->default('manual');
            $table->unsignedTinyInteger('evidence_confidence')->nullable();
            $table->string('safe_rationale', 1000);
            $table->string('evidence_reference', 512)->nullable();
            $table->char('evidence_hash', 64);
            $table->string('rules_version', 64)->nullable();
            $table->string('model_version', 128)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('stale_after')->nullable();
            $table->timestamps();

            $table->unique(
                ['unit_business_context_id', 'product_id', 'match_type'],
                'unit_product_match_unique',
            );
            $table->index(['unit_id', 'unit_business_context_id', 'status'], 'unit_product_context_status_idx');
            $table->index(['product_id', 'match_type', 'status'], 'unit_product_match_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_product_matches');
        Schema::dropIfExists('prospecting_candidate_products');
        Schema::dropIfExists('prospecting_search_job_products');
    }
};
