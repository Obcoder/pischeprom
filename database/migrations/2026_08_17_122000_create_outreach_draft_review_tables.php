<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_contact_context_link_id')->nullable()->constrained('unit_contact_context_links')->restrictOnDelete();
            $table->foreignId('email_id')->nullable()->constrained('emails')->restrictOnDelete();
            $table->foreignId('unit_product_match_id')->constrained('unit_product_matches')->restrictOnDelete();
            $table->foreignId('unit_good_match_id')->nullable()->constrained('unit_good_matches')->restrictOnDelete();
            $table->foreignId('product_relevance_snapshot_id')->nullable()->constrained('unit_product_relevance_snapshots')->restrictOnDelete();
            $table->foreignId('good_fit_snapshot_id')->nullable()->constrained('unit_good_fit_snapshots')->restrictOnDelete();
            $table->foreignId('prospect_priority_snapshot_id')->nullable()->constrained('unit_prospect_priority_snapshots')->restrictOnDelete();
            $table->string('purpose', 48);
            $table->string('status', 32)->default('draft');
            $table->string('generation_origin', 32)->default('manual');
            $table->string('template_profile', 64);
            $table->string('template_version', 32);
            $table->char('template_hash', 64);
            $table->char('policy_hash', 64);
            $table->char('input_hash', 64);
            $table->char('evidence_hash', 64);
            $table->unsignedInteger('current_revision_number')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['unit_business_context_id', 'status', 'created_at'], 'outreach_draft_context_idx');
            $table->index(['unit_product_match_id', 'status'], 'outreach_draft_product_idx');
            $table->index(['email_id', 'purpose', 'status'], 'outreach_draft_recipient_idx');
        });

        Schema::create('outreach_draft_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('outreach_draft_id')->constrained('outreach_drafts')->restrictOnDelete();
            $table->unsignedBigInteger('parent_revision_id')->nullable();
            $table->unsignedInteger('revision_number');
            $table->string('origin', 32);
            $table->json('structured_content');
            $table->string('subject', 255);
            $table->text('plaintext');
            $table->text('html');
            $table->string('renderer_version', 32);
            $table->char('renderer_hash', 64);
            $table->string('dlp_status', 24);
            $table->json('dlp_findings');
            $table->char('dlp_hash', 64);
            $table->char('claim_set_hash', 64);
            $table->char('input_hash', 64);
            $table->foreignId('edited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('parent_revision_id', 'outreach_revision_parent_fk')->references('id')->on('outreach_draft_revisions')->restrictOnDelete();
            $table->unique(['outreach_draft_id', 'revision_number'], 'outreach_draft_revision_unique');
            $table->index(['outreach_draft_id', 'created_at'], 'outreach_draft_revision_idx');
        });

        Schema::create('outreach_draft_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_draft_revision_id')->constrained('outreach_draft_revisions')->restrictOnDelete();
            $table->string('claim_type', 48);
            $table->char('text_fragment_hash', 64);
            $table->string('evidence_type', 48);
            $table->string('evidence_reference', 512);
            $table->char('evidence_hash', 64);
            $table->string('evidence_status', 24);
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->timestamp('fresh_until')->nullable();
            $table->string('review_status', 24)->default('pending');
            $table->string('safe_rationale', 500);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->char('audit_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['outreach_draft_revision_id', 'review_status'], 'outreach_claim_review_idx');
        });

        Schema::create('outreach_draft_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_draft_id')->constrained('outreach_drafts')->restrictOnDelete();
            $table->foreignId('outreach_draft_revision_id')->constrained('outreach_draft_revisions')->restrictOnDelete();
            $table->string('review_type', 24);
            $table->string('decision', 24);
            $table->string('reason_code', 64);
            $table->string('safe_note', 500)->nullable();
            $table->char('decision_hash', 64)->unique();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');

            $table->index(['outreach_draft_id', 'review_type', 'reviewed_at'], 'outreach_review_type_idx');
        });

        Schema::create('outreach_dispatch_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('outreach_draft_id')->constrained('outreach_drafts')->restrictOnDelete();
            $table->foreignId('outreach_draft_revision_id')->nullable()->constrained('outreach_draft_revisions')->restrictOnDelete();
            $table->foreignId('communication_permission_id')->nullable()->constrained('communication_permissions')->restrictOnDelete();
            $table->boolean('eligible')->default(false);
            $table->json('block_reasons');
            $table->char('decision_hash', 64)->unique();
            $table->string('policy_version', 64);
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('evaluated_at');

            $table->index(['outreach_draft_id', 'evaluated_at'], 'outreach_dispatch_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_dispatch_decisions');
        Schema::dropIfExists('outreach_draft_reviews');
        Schema::dropIfExists('outreach_draft_claims');
        Schema::dropIfExists('outreach_draft_revisions');
        Schema::dropIfExists('outreach_drafts');
    }
};
