<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_candidate_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->string('action', 24);
            $table->foreignId('existing_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('entity_name_snapshot')->nullable();
            $table->string('proposed_name');
            $table->json('proposed_attributes')->nullable();
            $table->text('evidence_summary');
            $table->json('duplicate_candidate_ids')->nullable();
            $table->string('status', 32)->default('review_required');
            $table->string('proposer_type', 16)->default('human');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['unit_id', 'unit_business_context_id', 'status'], 'entity_proposal_context_status_idx');
            $table->index(['existing_entity_id', 'status'], 'entity_proposal_existing_idx');
        });

        Schema::create('unit_dossier_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_name_snapshot');
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->nullOnDelete();
            $table->string('event_type', 96)->index();
            $table->string('subject_type', 48)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('actor_type', 16)->default('human');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary', 512);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['unit_id', 'created_at'], 'unit_dossier_audit_timeline_idx');
            $table->index(['subject_type', 'subject_id'], 'unit_dossier_audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_dossier_audit_events');
        Schema::dropIfExists('entity_candidate_proposals');
    }
};
