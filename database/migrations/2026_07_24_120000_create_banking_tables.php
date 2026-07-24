<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'bank_connections',
        'bank_oauth_attempts',
        'bank_accounts',
        'bank_account_balance_snapshots',
        'bank_transactions',
        'bank_transaction_revisions',
        'bank_sync_runs',
        'bank_sync_errors',
        'bank_match_suggestions',
        'bank_transaction_allocations',
        'bank_payment_order_drafts',
        'bank_audit_events',
    ];

    public function up(): void
    {
        $this->removeEmptyPartialSchema();

        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->index();
            $table->foreignId('owner_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('environment', 16)->default('sandbox');
            $table->string('status', 48)->default('pending')->index();
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('client_secret_expires_at')->nullable();
            $table->timestamp('mtls_certificate_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'owner_entity_id', 'environment'],
                'bank_connections_provider_owner_environment_unique'
            );
        });

        Schema::create('bank_oauth_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('environment', 16);
            $table->char('state_hash', 64)->unique();
            $table->char('nonce_hash', 64)->nullable();
            $table->foreignId('owner_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('callback_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->restrictOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('account_number', 34);
            $table->string('masked_number', 34);
            $table->string('name')->nullable();
            $table->string('type', 64)->nullable();
            $table->char('currency', 3)->default('RUB');
            $table->string('status', 32)->default('active')->index();
            $table->decimal('current_balance', 20, 2)->nullable();
            $table->timestamp('balance_as_of')->nullable();
            $table->date('balance_statement_date')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_incremental_cursor_at')->nullable();
            $table->json('normalized_requisites')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['bank_connection_id', 'account_number'],
                'bank_accounts_connection_number_unique'
            );
            $table->index(['bank_connection_id', 'status']);
        });

        Schema::create('bank_account_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64)->default('closing');
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3)->default('RUB');
            $table->date('statement_date')->nullable();
            $table->timestamp('as_of');
            $table->string('source', 64);
            $table->timestamps();

            $table->index(['bank_account_id', 'as_of']);
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('provider_operation_id', 255)->nullable();
            $table->char('fingerprint', 64)->unique();
            $table->date('operation_date')->index();
            $table->date('posting_date')->nullable()->index();
            $table->date('value_date')->nullable();
            $table->string('direction', 16)->index();
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3)->default('RUB');
            $table->string('status', 48)->default('posted')->index();
            $table->string('bank_document_number', 128)->nullable();
            $table->text('purpose')->nullable();
            $table->string('payer_name', 1024)->nullable();
            $table->string('payer_inn', 16)->nullable()->index();
            $table->string('payer_kpp', 16)->nullable();
            $table->string('payer_account', 34)->nullable()->index();
            $table->string('payer_bank_name', 1024)->nullable();
            $table->string('payer_bic', 16)->nullable();
            $table->string('payer_corr_account', 34)->nullable();
            $table->string('recipient_name', 1024)->nullable();
            $table->string('recipient_inn', 16)->nullable();
            $table->string('recipient_kpp', 16)->nullable();
            $table->string('recipient_account', 34)->nullable();
            $table->string('recipient_bank_name', 1024)->nullable();
            $table->string('recipient_bic', 16)->nullable();
            $table->string('recipient_corr_account', 34)->nullable();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->timestamp('bank_modified_at')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('imported_at');
            $table->string('reconciliation_status', 48)->default('unmatched')->index();
            $table->boolean('no_reconciliation_required')->default(false);
            $table->string('review_reason', 1024)->nullable();
            $table->text('manager_comment')->nullable();
            $table->timestamps();

            $table->unique(
                ['bank_account_id', 'provider_operation_id'],
                'bank_transactions_account_operation_unique'
            );
            $table->index(['bank_connection_id', 'operation_date']);
            $table->index(['direction', 'status', 'reconciliation_status'], 'bank_transactions_worklist_index');
        });

        Schema::create('bank_transaction_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->restrictOnDelete();
            $table->string('status', 48);
            $table->char('payload_hash', 64);
            $table->json('changed_fields')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('recorded_at');

            $table->unique(
                ['bank_transaction_id', 'payload_hash'],
                'bank_transaction_revisions_transaction_payload_unique'
            );
        });

        Schema::create('bank_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->string('cursor', 255)->nullable();
            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->uuid('correlation_id')->unique();
            $table->string('safe_error_message', 1024)->nullable();
            $table->timestamps();
        });

        Schema::create('bank_sync_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_sync_run_id')->constrained()->cascadeOnDelete();
            $table->string('category', 64)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('bank_cause', 128)->nullable();
            $table->string('safe_message', 1024);
            $table->string('endpoint_alias', 128)->nullable();
            $table->string('request_id', 255)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(1);
            $table->boolean('requires_intervention')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_match_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('suggestable', 'bank_suggestions_suggestable_index');
            $table->unsignedTinyInteger('score');
            $table->string('algorithm_version', 32);
            $table->json('rules');
            $table->string('status', 32)->default('pending')->index();
            $table->timestamps();

            $table->index(
                ['bank_transaction_id', 'suggestable_type', 'suggestable_id'],
                'bank_match_suggestions_candidate_index'
            );
        });

        Schema::create('bank_transaction_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->restrictOnDelete();
            $table->morphs('allocatable', 'bank_allocations_allocatable_index');
            $table->decimal('amount', 20, 2);
            $table->string('source', 32);
            $table->string('matching_rule', 128)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 1024)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(
                ['allocatable_type', 'allocatable_id', 'is_active'],
                'bank_allocations_receivable_active_index'
            );
        });

        Schema::create('bank_payment_order_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 64);
            $table->date('document_date');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('payer_bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('recipient_entity_id')->constrained('entities')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3)->default('RUB');
            $table->string('payer_name', 1024);
            $table->string('payer_inn', 16);
            $table->string('payer_kpp', 16)->nullable();
            $table->string('payer_account', 34);
            $table->string('payer_bank_name', 1024);
            $table->string('payer_bic', 16);
            $table->string('payer_corr_account', 34);
            $table->string('recipient_name', 1024);
            $table->string('recipient_inn', 16);
            $table->string('recipient_kpp', 16)->nullable();
            $table->string('recipient_account', 34);
            $table->string('recipient_bank_name', 1024);
            $table->string('recipient_bic', 16);
            $table->string('recipient_corr_account', 34);
            $table->text('purpose');
            $table->string('vat_type', 32)->default('included');
            $table->string('vat_rate', 16)->nullable();
            $table->decimal('vat_amount', 20, 2)->nullable();
            $table->unsignedTinyInteger('payment_priority')->default(5);
            $table->json('budget_fields')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->unique(['number', 'document_date'], 'bank_payment_drafts_number_date_unique');
        });

        Schema::create('bank_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 128)->index();
            $table->nullableMorphs('auditable', 'bank_audit_auditable_index');
            $table->uuid('correlation_id')->index();
            $table->json('metadata')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $this->dropBankingTables();
    }

    private function removeEmptyPartialSchema(): void
    {
        $existingTables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => Schema::hasTable($table),
        ));

        if ($existingTables === []) {
            return;
        }

        foreach ($existingTables as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "Cannot recover partial banking migration: table [{$table}] contains data."
                );
            }
        }

        $this->dropBankingTables();
    }

    private function dropBankingTables(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach (array_reverse(self::TABLES) as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
