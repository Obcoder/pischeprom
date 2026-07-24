<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Entity;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class BankingDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'banking.lock_store' => 'array',
            'queue.default' => 'sync',
            'inertia.ssr.enabled' => false,
            'banking.enabled' => true,
            'banking.sber.enabled' => true,
            'banking.sber.read_only' => true,
            'banking.sber.auto_match_enabled' => true,
            'banking.sber.auto_match_threshold' => 90,
            'banking.unidentified_notification_amount' => '100000.00',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');
        $this->createSchema();
    }

    protected function createUser(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Bank manager',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'status' => 'active',
            ...$attributes,
        ])->save();

        return $user->fresh();
    }

    protected function grantBankPermissions(User $user, string ...$permissions): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'crm',
            ]);
        }

        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createEntity(array $attributes = []): Entity
    {
        return Entity::query()->create([
            'name' => 'ООО Покупатель',
            'full_name' => 'Общество с ограниченной ответственностью Покупатель',
            'INN' => '7701234567',
            'KPP' => '770101001',
            'bank_account_number' => '40702810000000000099',
            ...$attributes,
        ]);
    }

    protected function createSale(Entity $entity, string $total = '100.00', array $attributes = []): Sale
    {
        $sale = new Sale;
        $sale->forceFill([
            'date' => '2026-07-20',
            'entity_id' => $entity->id,
            'payment_reference' => 'INV-100',
            'total' => $total,
            'payment_status' => 'unpaid',
            'paid_amount' => '0.00',
            'outstanding_amount' => $total,
            'overpaid_amount' => '0.00',
            ...$attributes,
        ])->save();

        return $sale->fresh();
    }

    protected function createConnection(array $attributes = []): BankConnection
    {
        return BankConnection::query()->create([
            'provider' => 'sber',
            'environment' => 'sandbox',
            'status' => BankConnectionStatus::Active,
            'scopes' => ['openid', 'GET_STATEMENT_ACCOUNT'],
            ...$attributes,
        ]);
    }

    protected function createAccount(BankConnection $connection, array $attributes = []): BankAccount
    {
        return BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_id' => 'account-1',
            'account_number' => '40702810000000000001',
            'masked_number' => '4070••••••••••••0001',
            'name' => 'Основной',
            'currency' => 'RUB',
            'status' => 'active',
            ...$attributes,
        ]);
    }

    protected function createTransaction(
        BankAccount $account,
        string $amount = '100.00',
        array $attributes = [],
    ): BankTransaction {
        $operationId = $attributes['provider_operation_id'] ?? fake()->uuid();

        return BankTransaction::query()->create([
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'provider_operation_id' => $operationId,
            'fingerprint' => hash('sha256', $account->id.'|'.$operationId),
            'operation_date' => '2026-07-24',
            'posting_date' => '2026-07-24',
            'direction' => BankTransactionDirection::Credit,
            'amount' => $amount,
            'currency' => 'RUB',
            'status' => BankTransactionStatus::Posted,
            'purpose' => 'Оплата по счёту № INV-100',
            'payer_name' => 'ООО Покупатель',
            'payer_inn' => '7701234567',
            'payer_account' => '40702810000000000099',
            'imported_at' => now(),
            'reconciliation_status' => ReconciliationStatus::Unmatched,
            ...$attributes,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('profile_photo_path')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('entity_classifications', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->timestamps();
        });
        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('full_name')->nullable();
            $table->unsignedBigInteger('entity_classification_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('INN', 16)->nullable();
            $table->string('KPP', 16)->nullable();
            $table->string('OGRN', 32)->nullable();
            $table->string('legal_address')->nullable();
            $table->string('bank_account_number', 34)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_bic', 16)->nullable();
            $table->string('bank_corr_account', 34)->nullable();
            $table->timestamps();
        });
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->string('address')->nullable();
            $table->timestamps();
        });
        Schema::create('building_entities', function (Blueprint $table): void {
            $table->unsignedBigInteger('building_id');
            $table->unsignedBigInteger('entity_id');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('entity_id');
            $table->string('payment_reference')->nullable()->unique();
            $table->decimal('total', 20, 2);
            $table->string('payment_status')->default('unpaid');
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('outstanding_amount', 20, 2)->default(0);
            $table->decimal('overpaid_amount', 20, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('entity_id');
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->unsignedBigInteger('owner_entity_id')->nullable();
            $table->string('environment');
            $table->string('status');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('client_secret_expires_at')->nullable();
            $table->timestamp('mtls_certificate_expires_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        Schema::create('bank_oauth_attempts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('provider');
            $table->string('environment');
            $table->string('state_hash', 64)->unique();
            $table->string('nonce_hash', 64)->nullable();
            $table->unsignedBigInteger('owner_entity_id')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('callback_error')->nullable();
            $table->timestamps();
        });
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_connection_id');
            $table->string('external_id')->nullable();
            $table->string('account_number', 34);
            $table->string('masked_number', 34);
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('currency', 3);
            $table->string('status');
            $table->decimal('current_balance', 20, 2)->nullable();
            $table->timestamp('balance_as_of')->nullable();
            $table->date('balance_statement_date')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_incremental_cursor_at')->nullable();
            $table->json('normalized_requisites')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamps();
        });
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_connection_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->string('provider_operation_id')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->date('operation_date');
            $table->date('posting_date')->nullable();
            $table->date('value_date')->nullable();
            $table->string('direction');
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('bank_document_number')->nullable();
            $table->text('purpose')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_inn')->nullable();
            $table->string('payer_kpp')->nullable();
            $table->string('payer_account')->nullable();
            $table->string('payer_bank_name')->nullable();
            $table->string('payer_bic')->nullable();
            $table->string('payer_corr_account')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_inn')->nullable();
            $table->string('recipient_kpp')->nullable();
            $table->string('recipient_account')->nullable();
            $table->string('recipient_bank_name')->nullable();
            $table->string('recipient_bic')->nullable();
            $table->string('recipient_corr_account')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamp('bank_modified_at')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamp('imported_at');
            $table->string('reconciliation_status');
            $table->boolean('no_reconciliation_required')->default(false);
            $table->string('review_reason')->nullable();
            $table->text('manager_comment')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'provider_operation_id']);
        });
        Schema::create('bank_transaction_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_transaction_id');
            $table->string('status');
            $table->string('payload_hash', 64);
            $table->json('changed_fields')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamp('recorded_at');
            $table->unique(['bank_transaction_id', 'payload_hash']);
        });
        Schema::create('bank_transaction_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_transaction_id');
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('amount', 20, 2);
            $table->string('source');
            $table->string('matching_rule')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('bank_match_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_transaction_id');
            $table->string('suggestable_type')->nullable();
            $table->unsignedBigInteger('suggestable_id')->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('algorithm_version');
            $table->json('rules');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('bank_payment_order_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->date('document_date');
            $table->string('status');
            $table->unsignedBigInteger('payer_bank_account_id');
            $table->unsignedBigInteger('recipient_entity_id');
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3);
            $table->string('payer_name');
            $table->string('payer_inn');
            $table->string('payer_kpp')->nullable();
            $table->string('payer_account');
            $table->string('payer_bank_name');
            $table->string('payer_bic');
            $table->string('payer_corr_account');
            $table->string('recipient_name');
            $table->string('recipient_inn');
            $table->string('recipient_kpp')->nullable();
            $table->string('recipient_account');
            $table->string('recipient_bank_name');
            $table->string('recipient_bic');
            $table->string('recipient_corr_account');
            $table->text('purpose');
            $table->string('vat_type');
            $table->string('vat_rate')->nullable();
            $table->decimal('vat_amount', 20, 2)->nullable();
            $table->unsignedTinyInteger('payment_priority');
            $table->json('budget_fields')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
            $table->unique(['number', 'document_date']);
        });
        Schema::create('bank_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('correlation_id');
            $table->json('metadata')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64)->unique();
            $table->timestamp('created_at');
        });
    }
}
