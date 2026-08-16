<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('prospecting_search_job_id')->nullable()->constrained('prospecting_search_jobs')->nullOnDelete();
            $table->foreignId('prospecting_search_query_id')->nullable()->constrained('prospecting_search_queries')->nullOnDelete();
            $table->foreignId('ai_agent_run_id')->nullable()->constrained('ai_agent_runs')->nullOnDelete();
            $table->string('purpose', 32);
            $table->string('lane', 24);
            $table->string('role_code', 32);
            $table->string('working_name', 255);
            $table->string('normalized_name', 255);
            $table->string('normalized_domain', 255)->nullable();
            $table->string('canonical_website', 2048)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('location_display', 255)->nullable();
            $table->string('public_activity_summary', 1000)->nullable();
            $table->string('relevance_summary', 1000)->nullable();
            $table->json('confidence_components')->nullable();
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->char('fingerprint_hash', 64);
            $table->char('normalized_payload_hash', 64);
            $table->string('status', 32)->default('pending_resolution');
            $table->string('resolution_outcome', 32)->nullable();
            $table->foreignId('resolved_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_reason_code', 96)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('anonymized_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['prospecting_search_job_id', 'fingerprint_hash'], 'prospecting_candidate_fingerprint_unique');
            $table->index(['lane', 'status', 'expires_at'], 'prospecting_candidate_review_idx');
            $table->index(['normalized_domain', 'status'], 'prospecting_candidate_domain_idx');
            $table->index(['resolved_unit_id', 'status'], 'prospecting_candidate_unit_idx');
        });

        Schema::create('prospecting_candidate_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id')->constrained('prospecting_candidates')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->string('canonical_url', 2048)->nullable();
            $table->string('source_reference', 512)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('source_domain', 255)->nullable();
            $table->string('bounded_excerpt', 1000)->nullable();
            $table->char('evidence_hash', 64);
            $table->timestamp('accessed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('data_classification', 32)->default('public');
            $table->string('visibility_scope', 32);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->unsignedTinyInteger('source_quality')->nullable();
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'evidence_hash'], 'prospecting_candidate_source_unique');
            $table->index(['prospecting_candidate_id', 'source_domain'], 'prospecting_source_domain_idx');
        });

        Schema::create('prospecting_candidate_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id')->constrained('prospecting_candidates')->cascadeOnDelete();
            $table->foreignId('prospecting_candidate_source_id')->nullable()->constrained('prospecting_candidate_sources')->nullOnDelete();
            $table->string('channel_kind', 16);
            $table->char('normalized_hash', 64);
            $table->text('protected_value');
            $table->string('masked_display', 255);
            $table->string('contact_role', 32)->default('business_general');
            $table->string('verification_status', 24)->default('unverified');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('data_classification', 32);
            $table->string('communication_state', 24)->default('review_required');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'channel_kind', 'normalized_hash'], 'prospecting_candidate_channel_unique');
            $table->index(['communication_state', 'data_classification'], 'prospecting_channel_policy_idx');
        });

        Schema::create('prospecting_candidate_unit_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospecting_candidate_id')->constrained('prospecting_candidates')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('signal_code', 64);
            $table->unsignedTinyInteger('strength');
            $table->unsignedSmallInteger('rank')->default(1);
            $table->char('evidence_hash', 64);
            $table->string('evidence_reference', 512)->nullable();
            $table->string('review_status', 24)->default('suggested');
            $table->timestamps();

            $table->unique(['prospecting_candidate_id', 'unit_id', 'signal_code'], 'prospecting_candidate_unit_signal_unique');
            $table->index(['prospecting_candidate_id', 'rank'], 'prospecting_candidate_match_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_candidate_unit_matches');
        Schema::dropIfExists('prospecting_candidate_channels');
        Schema::dropIfExists('prospecting_candidate_sources');
        Schema::dropIfExists('prospecting_candidates');
    }
};
