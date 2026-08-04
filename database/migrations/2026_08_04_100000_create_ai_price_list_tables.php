<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_webhook_events', function (Blueprint $table): void {
            $table->char('deduplication_key', 64)->nullable()->unique()->after('update_id');
        });

        Schema::create('price_list_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_key', 191)->unique();
            $table->string('source_channel', 16)->index();
            $table->string('status', 40)->index();
            $table->string('current_stage', 40)->nullable()->index();
            $table->unsignedTinyInteger('progress')->default(0);

            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('mail_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('price_list_imports')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source_external_message_id', 191)->nullable()->index();
            $table->string('source_external_attachment_id', 191)->nullable();
            $table->string('source_chat_id', 96)->nullable()->index();
            $table->string('source_user_id', 96)->nullable()->index();
            $table->string('sender_address')->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->text('source_subject')->nullable();
            $table->timestamp('source_received_at')->nullable()->index();

            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('safe_name');
            $table->string('extension', 16)->nullable()->index();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256', 64)->nullable()->index();

            $table->string('document_class', 32)->default('uncertain')->index();
            $table->string('document_type', 32)->nullable();
            $table->string('parser_type', 64)->nullable();
            $table->boolean('requires_ocr')->default(false);
            $table->string('extractor_version', 32)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->string('schema_version', 32)->nullable();
            $table->string('model_id', 191)->nullable();
            $table->json('document_defaults')->nullable();
            $table->json('document_metadata')->nullable();

            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_exact')->default(0);
            $table->unsignedInteger('items_probable')->default(0);
            $table->unsignedInteger('items_unmatched')->default(0);
            $table->unsignedInteger('items_invalid')->default(0);
            $table->unsignedInteger('items_applied')->default(0);
            $table->unsignedInteger('ocr_pages')->default(0);

            $table->string('error_code', 96)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->boolean('error_retryable')->default(false)->index();
            $table->timestamp('stage_started_at')->nullable();
            $table->timestamp('stage_heartbeat_at')->nullable()->index();
            $table->timestamp('processing_completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['source_channel', 'source_external_message_id'], 'price_list_source_message_idx');
            $table->index(['status', 'stage_heartbeat_at'], 'price_list_recovery_idx');
            $table->index(['entity_id', 'source_received_at'], 'price_list_supplier_received_idx');
        });

        Schema::create('price_list_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_import_id')->constrained('price_list_imports')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('source_sheet')->nullable();
            $table->unsignedInteger('source_page')->nullable();
            $table->unsignedInteger('source_table')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_range', 96)->nullable();
            $table->json('raw_cells')->nullable();
            $table->text('raw_text')->nullable();

            $table->string('raw_name')->nullable();
            $table->string('normalized_name')->nullable()->index();
            $table->string('supplier_sku', 191)->nullable()->index();
            $table->string('manufacturer_sku', 191)->nullable()->index();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('brand')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('package_description')->nullable();
            $table->decimal('units_per_package', 18, 6)->nullable();
            $table->decimal('net_quantity', 18, 6)->nullable();
            $table->string('net_quantity_unit', 32)->nullable();
            $table->decimal('price_basis_quantity', 18, 6)->nullable();
            $table->string('price_basis_unit', 32)->nullable();
            $table->decimal('minimum_order_quantity', 18, 6)->nullable();
            $table->decimal('price', 20, 6)->nullable();
            $table->char('currency_code', 3)->nullable()->index();
            $table->string('vat_mode', 16)->default('unknown');
            $table->decimal('vat_rate', 7, 4)->nullable();
            $table->string('availability')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('notes')->nullable();
            $table->json('field_evidence')->nullable();
            $table->json('warnings')->nullable();
            $table->char('row_fingerprint', 64);

            $table->string('decision_status', 24)->default('unreviewed')->index();
            $table->string('match_class', 24)->default('no_match')->index();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('match_method', 64)->nullable();
            $table->decimal('match_score', 7, 4)->nullable();
            $table->text('review_reason')->nullable();
            $table->json('user_corrections')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['price_list_import_id', 'row_fingerprint'], 'price_list_item_fingerprint_unique');
            $table->index(['price_list_import_id', 'position'], 'price_list_item_position_idx');
            $table->index(['price_list_import_id', 'match_class'], 'price_list_item_match_idx');
            $table->index(['price_list_import_id', 'decision_status'], 'price_list_item_decision_idx');
        });

        Schema::create('price_list_item_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_import_item_id')->constrained('price_list_import_items')->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('method', 64);
            $table->decimal('score', 7, 4);
            $table->json('score_components')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->boolean('is_rejected')->default(false);
            $table->timestamps();

            $table->unique(['price_list_import_item_id', 'good_id'], 'price_list_candidate_unique');
            $table->index(['price_list_import_item_id', 'rank'], 'price_list_candidate_rank_idx');
        });

        Schema::create('supplier_product_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->string('supplier_sku', 191)->nullable();
            $table->string('alias');
            $table->string('normalized_alias')->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'normalized_alias'], 'supplier_alias_name_unique');
            $table->unique(['entity_id', 'supplier_sku'], 'supplier_alias_sku_unique');
        });

        Schema::create('price_list_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_import_id')->constrained('price_list_imports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id')->index();
            $table->string('event_type', 96)->index();
            $table->string('stage', 40)->nullable()->index();
            $table->string('status_from', 40)->nullable();
            $table->string('status_to', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['price_list_import_id', 'created_at'], 'price_list_event_timeline_idx');
        });

        Schema::create('ai_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_import_id')->nullable()->constrained('price_list_imports')->nullOnDelete();
            $table->uuid('job_uuid')->nullable()->index();
            $table->string('provider', 64)->index();
            $table->string('operation', 64)->index();
            $table->string('model', 191)->nullable();
            $table->string('external_request_id', 191)->nullable()->index();
            $table->string('prompt_version', 32)->nullable();
            $table->string('schema_version', 32)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('units')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('status', 24)->index();
            $table->string('error_code', 96)->nullable();
            $table->decimal('estimated_cost', 16, 6)->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->boolean('cost_is_estimate')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at'], 'ai_usage_provider_date_idx');
            $table->index(['price_list_import_id', 'created_at'], 'ai_usage_import_date_idx');
        });

        Schema::create('supplier_good_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->restrictOnDelete();
            $table->foreignId('good_id')->constrained('goods')->restrictOnDelete();
            $table->foreignId('price_list_import_id')->constrained('price_list_imports')->restrictOnDelete();
            $table->foreignId('price_list_import_item_id')->unique()->constrained('price_list_import_items')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('price', 20, 6);
            $table->char('currency_code', 3);
            $table->string('vat_mode', 16)->default('unknown');
            $table->decimal('vat_rate', 7, 4)->nullable();
            $table->decimal('price_basis_quantity', 18, 6)->nullable();
            $table->string('price_basis_unit', 32)->nullable();
            $table->decimal('minimum_order_quantity', 18, 6)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('supplier_sku', 191)->nullable();
            $table->char('idempotency_key', 64)->unique();
            $table->json('provenance')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_id', 'good_id', 'created_at'], 'supplier_good_price_history_idx');
            $table->index(['good_id', 'currency_code'], 'supplier_good_price_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_good_prices');
        Schema::dropIfExists('ai_usage_records');
        Schema::dropIfExists('price_list_events');
        Schema::dropIfExists('supplier_product_aliases');
        Schema::dropIfExists('price_list_item_candidates');
        Schema::dropIfExists('price_list_import_items');
        Schema::dropIfExists('price_list_imports');

        Schema::table('max_webhook_events', function (Blueprint $table): void {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn('deduplication_key');
        });
    }
};
