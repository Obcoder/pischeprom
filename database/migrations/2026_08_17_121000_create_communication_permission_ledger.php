<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('unit_contact_context_link_id')->constrained('unit_contact_context_links')->restrictOnDelete();
            $table->foreignId('email_id')->constrained('emails')->restrictOnDelete();
            $table->string('channel', 16)->default('email');
            $table->char('endpoint_hash', 64);
            $table->string('sender_scope', 64);
            $table->string('purpose', 48);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('product_category_scope', 128)->nullable();
            $table->string('status', 24)->default('pending_review');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('policy_version', 64);
            $table->char('policy_hash', 64);
            $table->char('evidence_set_hash', 64)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['unit_business_context_id', 'purpose', 'status'], 'communication_permission_context_idx');
            $table->index(['endpoint_hash', 'channel', 'status'], 'communication_permission_endpoint_idx');
            $table->index(['product_id', 'purpose', 'status'], 'communication_permission_product_idx');
        });

        Schema::create('communication_permission_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_permission_id')->constrained('communication_permissions')->restrictOnDelete();
            $table->string('evidence_type', 48);
            $table->string('safe_reference', 512);
            $table->char('content_hash', 64);
            $table->char('scope_hash', 64);
            $table->timestamp('captured_at');
            $table->string('source_controller', 128)->nullable();
            $table->string('safe_note', 500)->nullable();
            $table->char('audit_hash', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['communication_permission_id', 'evidence_type'], 'communication_permission_evidence_idx');
        });

        Schema::create('communication_permission_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_permission_id')->constrained('communication_permissions')->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->string('reason_code', 64);
            $table->string('safe_note', 500)->nullable();
            $table->char('evidence_set_hash', 64)->nullable();
            $table->char('decision_hash', 64)->unique();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');

            $table->index(['communication_permission_id', 'decided_at'], 'communication_permission_decision_idx');
        });

        Schema::create('communication_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('scope', 24);
            $table->string('channel', 16)->default('email');
            $table->char('endpoint_hash', 64)->nullable();
            $table->char('domain_hash', 64)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->nullable()->constrained('unit_business_contexts')->restrictOnDelete();
            $table->string('reason', 48);
            $table->string('source', 64);
            $table->string('safe_evidence_reference', 512)->nullable();
            $table->char('evidence_hash', 64);
            $table->timestamp('active_from');
            $table->timestamp('active_until')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->string('clear_reason_code', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('audit_hash', 64)->unique();
            $table->timestamps();

            $table->index(['scope', 'channel', 'active_from', 'active_until'], 'communication_suppression_active_idx');
            $table->index(['endpoint_hash', 'channel', 'cleared_at'], 'communication_suppression_endpoint_idx');
            $table->index(['unit_business_context_id', 'cleared_at'], 'communication_suppression_context_idx');
        });

        Schema::create('communication_suppression_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_suppression_id')->constrained('communication_suppressions')->restrictOnDelete();
            $table->string('action', 24);
            $table->string('reason_code', 64);
            $table->string('safe_note', 500)->nullable();
            $table->char('decision_hash', 64)->unique();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');

            $table->index(['communication_suppression_id', 'decided_at'], 'communication_suppression_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_suppression_decisions');
        Schema::dropIfExists('communication_suppressions');
        Schema::dropIfExists('communication_permission_decisions');
        Schema::dropIfExists('communication_permission_evidence');
        Schema::dropIfExists('communication_permissions');
    }
};
