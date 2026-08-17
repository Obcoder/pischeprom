<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sendings', function (Blueprint $table): void {
            $table->foreignId('mail_message_id')->nullable()->after('email_id')->constrained('mail_messages')->restrictOnDelete();
            $table->string('request_profile', 64)->nullable()->index()->after('provider_message_id');
            $table->char('request_hash', 64)->nullable()->index()->after('request_profile');
            $table->char('response_hash', 64)->nullable()->index()->after('request_hash');
            $table->string('http_status_category', 16)->nullable()->after('response_hash');
            $table->string('safe_request_id', 191)->nullable()->index()->after('http_status_category');
            $table->string('safe_error_code', 64)->nullable()->index()->after('safe_request_id');
            $table->string('safe_summary', 255)->nullable()->after('safe_error_code');
            $table->timestamp('ambiguous_acceptance_at')->nullable()->index()->after('safe_summary');

            $table->unique('mail_message_id', 'sendings_mail_message_unique');
        });

        Schema::create('outreach_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('unit_business_context_id')->constrained('unit_business_contexts')->restrictOnDelete();
            $table->foreignId('outreach_draft_id')->constrained('outreach_drafts')->restrictOnDelete();
            $table->foreignId('outreach_draft_revision_id')->constrained('outreach_draft_revisions')->restrictOnDelete();
            $table->foreignId('communication_permission_id')->constrained('communication_permissions')->restrictOnDelete();
            $table->foreignId('unit_contact_context_link_id')->constrained('unit_contact_context_links')->restrictOnDelete();
            $table->foreignId('unit_product_match_id')->constrained('unit_product_matches')->restrictOnDelete();
            $table->foreignId('unit_good_match_id')->nullable()->constrained('unit_good_matches')->restrictOnDelete();
            $table->foreignId('mail_message_id')->nullable()->unique()->constrained('mail_messages')->restrictOnDelete();
            $table->foreignId('sending_id')->nullable()->unique()->constrained('sendings')->restrictOnDelete();
            $table->string('purpose', 48);
            $table->string('state', 32)->default('prepared')->index();
            $table->string('request_profile', 64)->default('outreach_zero_retry');
            $table->char('idempotency_hash', 64)->unique();
            $table->char('revision_hash', 64);
            $table->char('renderer_hash', 64);
            $table->char('dlp_hash', 64);
            $table->char('evidence_hash', 64);
            $table->char('permission_scope_hash', 64);
            $table->char('sender_config_hash', 64);
            $table->char('unsubscribe_token_hash', 64)->unique();
            $table->char('last_revalidation_hash', 64)->nullable();
            $table->string('last_block_reason', 64)->nullable()->index();
            $table->string('safe_summary', 255)->nullable();
            $table->string('provider_job_id', 191)->nullable()->index();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('queued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('prepared_at');
            $table->timestamp('last_revalidated_at')->nullable()->index();
            $table->timestamp('queue_requested_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('provider_accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('ambiguous_acceptance_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['outreach_draft_revision_id', 'unit_contact_context_link_id', 'purpose'],
                'outreach_dispatch_revision_recipient_unique',
            );
            $table->index(['unit_business_context_id', 'state', 'created_at'], 'outreach_dispatch_context_state_idx');
            $table->index(['unit_id', 'state', 'created_at'], 'outreach_dispatch_unit_state_idx');
        });

        Schema::table('outreach_dispatch_decisions', function (Blueprint $table): void {
            $table->foreignId('outreach_dispatch_id')
                ->nullable()
                ->after('outreach_draft_revision_id')
                ->constrained('outreach_dispatches')
                ->restrictOnDelete();
            $table->string('checkpoint', 24)->default('eligibility_preview')->index()->after('outreach_dispatch_id');
        });

        Schema::create('outreach_reply_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('outreach_dispatch_id')->constrained('outreach_dispatches')->restrictOnDelete();
            $table->foreignId('incoming_mail_message_id')->unique()->constrained('mail_messages')->restrictOnDelete();
            $table->string('correlation_method', 32);
            $table->char('correlation_hash', 64)->unique();
            $table->string('triage_profile', 64)->default('outreach_reply_triage.v1');
            $table->string('triage_status', 24)->default('review_required')->index();
            $table->string('triage_class', 32)->nullable()->index();
            $table->char('triage_hash', 64)->nullable();
            $table->string('safe_reason_code', 64)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['outreach_dispatch_id', 'incoming_mail_message_id'], 'outreach_reply_dispatch_message_unique');
        });

        Schema::create('outreach_follow_up_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('outreach_dispatch_id')->unique()->constrained('outreach_dispatches')->restrictOnDelete();
            $table->string('status', 32)->default('not_planned')->index();
            $table->unsignedTinyInteger('max_follow_ups')->default(0);
            $table->timestamp('earliest_at')->nullable();
            $table->string('recommendation_code', 64)->nullable();
            $table->string('cancellation_reason', 64)->nullable()->index();
            $table->char('recommendation_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_follow_up_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_follow_up_plan_id')->constrained('outreach_follow_up_plans')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('status', 32)->default('draft_required')->index();
            $table->timestamp('earliest_at')->nullable();
            $table->json('required_reviews');
            $table->foreignId('outreach_draft_id')->nullable()->constrained('outreach_drafts')->restrictOnDelete();
            $table->foreignId('outreach_draft_revision_id')->nullable()->constrained('outreach_draft_revisions')->restrictOnDelete();
            $table->string('safe_reason_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['outreach_follow_up_plan_id', 'position'], 'outreach_follow_up_step_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_follow_up_steps');
        Schema::dropIfExists('outreach_follow_up_plans');
        Schema::dropIfExists('outreach_reply_links');

        Schema::table('outreach_dispatch_decisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('outreach_dispatch_id');
            $table->dropIndex(['checkpoint']);
            $table->dropColumn('checkpoint');
        });

        Schema::dropIfExists('outreach_dispatches');

        Schema::table('sendings', function (Blueprint $table): void {
            $table->dropUnique('sendings_mail_message_unique');
            $table->dropConstrainedForeignId('mail_message_id');
            $table->dropIndex(['request_profile']);
            $table->dropIndex(['request_hash']);
            $table->dropIndex(['response_hash']);
            $table->dropIndex(['safe_request_id']);
            $table->dropIndex(['safe_error_code']);
            $table->dropIndex(['ambiguous_acceptance_at']);
            $table->dropColumn([
                'request_profile', 'request_hash', 'response_hash', 'http_status_category',
                'safe_request_id', 'safe_error_code', 'safe_summary', 'ambiguous_acceptance_at',
            ]);
        });
    }
};
