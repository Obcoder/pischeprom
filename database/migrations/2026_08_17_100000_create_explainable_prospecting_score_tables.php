<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_product_relevance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_product_match_id')->constrained('unit_product_matches')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $this->snapshotColumns($table, 'product_relevance');
            $table->foreign('base_snapshot_id', 'product_relevance_base_fk')->references('id')->on('unit_product_relevance_snapshots')->restrictOnDelete();
            $table->foreign('superseded_by_snapshot_id', 'product_relevance_superseded_fk')->references('id')->on('unit_product_relevance_snapshots')->restrictOnDelete();
            $table->index(['unit_product_match_id', 'definition_code', 'created_at'], 'product_relevance_subject_idx');
            $table->index(['unit_business_context_id', 'stale_at', 'superseded_at'], 'product_relevance_context_idx');
        });

        Schema::create('unit_product_relevance_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_product_relevance_snapshot_id')->constrained('unit_product_relevance_snapshots')->restrictOnDelete();
            $this->factorColumns($table, 'product_relevance');
            $table->unique(['unit_product_relevance_snapshot_id', 'factor_code'], 'product_relevance_factor_unique');
        });

        Schema::create('unit_good_fit_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_good_match_id')->constrained('unit_good_matches')->restrictOnDelete();
            $table->foreignId('unit_product_match_id')->constrained('unit_product_matches')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $this->snapshotColumns($table, 'good_fit');
            $table->foreign('base_snapshot_id', 'good_fit_base_fk')->references('id')->on('unit_good_fit_snapshots')->restrictOnDelete();
            $table->foreign('superseded_by_snapshot_id', 'good_fit_superseded_fk')->references('id')->on('unit_good_fit_snapshots')->restrictOnDelete();
            $table->index(['unit_good_match_id', 'definition_code', 'created_at'], 'good_fit_subject_idx');
            $table->index(['unit_product_match_id', 'stale_at', 'superseded_at'], 'good_fit_product_idx');
        });

        Schema::create('unit_good_fit_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_good_fit_snapshot_id')->constrained('unit_good_fit_snapshots')->restrictOnDelete();
            $this->factorColumns($table, 'good_fit');
            $table->unique(['unit_good_fit_snapshot_id', 'factor_code'], 'good_fit_factor_unique');
        });

        Schema::create('unit_prospect_priority_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $this->snapshotColumns($table, 'prospect_priority');
            $table->foreign('base_snapshot_id', 'prospect_priority_base_fk')->references('id')->on('unit_prospect_priority_snapshots')->restrictOnDelete();
            $table->foreign('superseded_by_snapshot_id', 'prospect_priority_superseded_fk')->references('id')->on('unit_prospect_priority_snapshots')->restrictOnDelete();
            $table->index(['unit_business_context_id', 'definition_code', 'created_at'], 'prospect_priority_subject_idx');
            $table->index(['unit_id', 'stale_at', 'superseded_at'], 'prospect_priority_unit_idx');
        });

        Schema::create('unit_prospect_priority_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_prospect_priority_snapshot_id')->constrained('unit_prospect_priority_snapshots')->restrictOnDelete();
            $this->factorColumns($table, 'prospect_priority');
            $table->unique(['unit_prospect_priority_snapshot_id', 'factor_code'], 'prospect_priority_factor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_prospect_priority_factors');
        Schema::dropIfExists('unit_prospect_priority_snapshots');
        Schema::dropIfExists('unit_good_fit_factors');
        Schema::dropIfExists('unit_good_fit_snapshots');
        Schema::dropIfExists('unit_product_relevance_factors');
        Schema::dropIfExists('unit_product_relevance_snapshots');
    }

    private function snapshotColumns(Blueprint $table, string $prefix): void
    {
        $table->unsignedTinyInteger('computed_score');
        $table->unsignedTinyInteger('effective_score');
        $table->unsignedTinyInteger('confidence');
        $table->string('band', 16);
        $table->string('eligibility', 32);
        $table->string('review_status', 24);
        $table->string('next_best_action', 64);
        $table->string('definition_code', 64);
        $table->string('definition_version', 32);
        $table->char('definition_hash', 64);
        $table->char('input_hash', 64);
        $table->char('evidence_hash', 64);
        $table->char('idempotency_key', 64)->unique();
        $table->string('origin', 32)->default('deterministic');
        $table->unsignedBigInteger('base_snapshot_id')->nullable();
        $table->string('override_reason_code', 64)->nullable();
        $table->string('override_safe_note', 500)->nullable();
        $table->timestamp('override_expires_at')->nullable();
        $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamp('stale_at')->nullable();
        $table->string('stale_reason_code', 64)->nullable();
        $table->timestamp('superseded_at')->nullable();
        $table->unsignedBigInteger('superseded_by_snapshot_id')->nullable();
        $table->timestamps();

        $table->index(['definition_code', 'definition_version', 'definition_hash'], $prefix.'_definition_idx');
        $table->index(['eligibility', 'review_status', 'band'], $prefix.'_queue_idx');
    }

    private function factorColumns(Blueprint $table, string $prefix): void
    {
        $table->string('factor_code', 64);
        $table->string('polarity', 16);
        $table->string('normalized_state', 64);
        $table->unsignedTinyInteger('weight');
        $table->smallInteger('contribution');
        $table->unsignedTinyInteger('confidence');
        $table->string('evidence_type', 32)->nullable();
        $table->string('evidence_reference', 512)->nullable();
        $table->char('evidence_hash', 64)->nullable();
        $table->timestamp('evidence_at')->nullable();
        $table->string('status', 16);
        $table->string('safe_rationale', 1000);
        $table->timestamps();

        $table->index(['factor_code', 'status'], $prefix.'_factor_lookup_idx');
    }
};
