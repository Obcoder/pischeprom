<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('normalized_email')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('company_name')->nullable()->index();
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('contact_source')->nullable();
            $table->string('consent_status')->default('unknown')->index();
            $table->string('consent_source')->nullable();
            $table->dateTime('consent_date')->nullable();
            $table->string('consent_proof_url', 2048)->nullable();
            $table->text('consent_proof_note')->nullable();
            $table->text('legal_basis_note')->nullable();
            $table->unsignedBigInteger('responsible_manager_id')->nullable()->index();
            $table->boolean('do_not_email')->default(false)->index();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->dateTime('complained_at')->nullable();
            $table->dateTime('hard_bounced_at')->nullable();
            $table->dateTime('soft_bounced_at')->nullable();
            $table->unsignedInteger('soft_bounce_count')->default(0);
            $table->dateTime('last_contacted_at')->nullable();
            $table->dateTime('last_opened_at')->nullable();
            $table->dateTime('last_clicked_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_contact_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('manual')->index();
            $table->json('filter_definition')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('mailing_contact_set_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('mailing_contact_sets')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('mailing_contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->dateTime('added_at')->useCurrent();
            $table->string('status')->default('active')->index();
            $table->unique(['set_id', 'contact_id']);
        });

        Schema::create('mailing_contact_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('source_type')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->json('errors_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('commercial_offer')->index();
            $table->string('subject', 998);
            $table->string('preheader')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->json('editor_schema')->nullable();
            $table->longText('html_markup');
            $table->text('plaintext')->nullable();
            $table->string('template_engine')->default('local');
            $table->string('unisender_template_id')->nullable()->index();
            $table->boolean('track_links')->default(true);
            $table->boolean('track_read')->default(true);
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('mailing_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('subject', 998);
            $table->string('preheader')->nullable();
            $table->json('editor_schema')->nullable();
            $table->longText('html_markup');
            $table->text('plaintext')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['template_id', 'version_number']);
        });

        Schema::create('mailing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('mass_offer')->index();
            $table->string('status')->default('draft')->index();
            $table->string('subject', 998);
            $table->string('preheader')->nullable();
            $table->foreignId('template_id')->nullable()->index()->constrained('mailing_templates')->nullOnDelete();
            $table->foreignId('contact_set_id')->nullable()->index()->constrained('mailing_contact_sets')->nullOnDelete();
            $table->longText('html_markup')->nullable();
            $table->text('plaintext')->nullable();
            $table->string('from_email');
            $table->string('from_name');
            $table->string('reply_to')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('compliance_status')->default('draft')->index();
            $table->text('compliance_note')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('unique_opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('unique_clicked_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->unsignedInteger('soft_bounced_count')->default(0);
            $table->unsignedInteger('hard_bounced_count')->default(0);
            $table->unsignedInteger('spam_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->index()->constrained('mailing_contacts')->nullOnDelete();
            $table->string('email')->index();
            $table->string('normalized_email')->index();
            $table->string('status')->default('pending')->index();
            $table->text('skip_reason')->nullable();
            $table->json('substitutions')->nullable();
            $table->json('metadata')->nullable();
            $table->longText('personal_html_markup')->nullable();
            $table->text('personal_plaintext')->nullable();
            $table->string('unsubscribe_token')->unique();
            $table->string('unisender_job_id')->nullable()->index();
            $table->string('idempotence_key')->nullable()->unique();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('first_opened_at')->nullable();
            $table->dateTime('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->dateTime('first_clicked_at')->nullable();
            $table->dateTime('last_clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->text('last_clicked_url')->nullable();
            $table->dateTime('unsubscribed_at')->nullable();
            $table->dateTime('soft_bounced_at')->nullable();
            $table->dateTime('hard_bounced_at')->nullable();
            $table->dateTime('spam_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('delivery_info')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'normalized_email']);
        });

        Schema::create('mailing_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('unisender_go')->index();
            $table->foreignId('campaign_id')->nullable()->index()->constrained('mailing_campaigns')->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->index()->constrained('mailing_campaign_recipients')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->index()->constrained('mailing_contacts')->nullOnDelete();
            $table->string('email')->index();
            $table->string('subject', 998);
            $table->string('status')->default('queued')->index();
            $table->string('unisender_job_id')->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('failed_emails')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('unisender_go')->index();
            $table->string('event_fingerprint')->unique();
            $table->foreignId('campaign_id')->nullable()->index()->constrained('mailing_campaigns')->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->index()->constrained('mailing_campaign_recipients')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->index()->constrained('mailing_contacts')->nullOnDelete();
            $table->string('unisender_job_id')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('event_name')->index();
            $table->string('status')->nullable()->index();
            $table->dateTime('event_time')->nullable();
            $table->text('url')->nullable();
            $table->string('delivery_status')->nullable();
            $table->text('destination_response')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('sender_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('mailing_suppression_list', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('normalized_email')->index();
            $table->string('cause')->index();
            $table->string('source')->default('local')->index();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->index()->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->index()->constrained('mailing_campaign_recipients')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->index()->constrained('mailing_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('item_type')->default('product')->index();
            $table->string('title');
            $table->string('sku')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('canonical_url')->nullable();
            $table->decimal('original_price', 16, 4)->nullable();
            $table->decimal('offer_price', 16, 4)->nullable();
            $table->string('currency', 10)->default('RUB');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('mailing_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->index()->constrained('mailing_campaign_recipients')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->text('original_url');
            $table->text('canonical_url')->nullable();
            $table->text('utm_url')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('unique_click_count')->default(0);
            $table->timestamps();
        });

        Schema::create('mailing_webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('unisender_go')->index();
            $table->boolean('auth_valid')->default(false);
            $table->longText('raw_payload');
            $table->json('parsed_payload')->nullable();
            $table->unsignedInteger('events_count')->default(0);
            $table->dateTime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('mailing_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_audit_log');
        Schema::dropIfExists('mailing_webhook_calls');
        Schema::dropIfExists('mailing_links');
        Schema::dropIfExists('mailing_offer_items');
        Schema::dropIfExists('mailing_suppression_list');
        Schema::dropIfExists('mailing_events');
        Schema::dropIfExists('mailing_messages');
        Schema::dropIfExists('mailing_campaign_recipients');
        Schema::dropIfExists('mailing_campaigns');
        Schema::dropIfExists('mailing_template_versions');
        Schema::dropIfExists('mailing_templates');
        Schema::dropIfExists('mailing_contact_imports');
        Schema::dropIfExists('mailing_contact_set_members');
        Schema::dropIfExists('mailing_contact_sets');
        Schema::dropIfExists('mailing_contacts');
    }
};
