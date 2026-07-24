<?php

namespace Tests\Feature\Banking;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankingMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_address')->nullable();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('entity_id');
            $table->double('total')->default(0);
        });
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('entity_id');
            $table->double('amount')->default(0);
        });
    }

    public function test_banking_migrations_create_the_expected_schema_without_external_services(): void
    {
        DB::table('entities')->insert(['id' => 1]);
        DB::table('sales')->insert([
            'id' => 1,
            'date' => '2026-07-24',
            'entity_id' => 1,
            'total' => '125.40',
        ]);
        $bankingTables = require database_path(
            'migrations/2026_07_24_120000_create_banking_tables.php'
        );
        $receivableFields = require database_path(
            'migrations/2026_07_24_120100_add_banking_fields_to_sales_and_entities.php'
        );

        $bankingTables->up();
        $receivableFields->up();

        foreach ([
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
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('sales', [
            'payment_reference',
            'payment_status',
            'paid_amount',
            'outstanding_amount',
            'overpaid_amount',
            'paid_at',
        ]));
        $this->assertTrue(Schema::hasColumns('entities', [
            'bank_account_number',
            'bank_name',
            'bank_bic',
            'bank_corr_account',
        ]));
        $this->assertContains(
            Schema::getColumnType('sales', 'total'),
            ['decimal', 'numeric']
        );
        $this->assertContains(
            Schema::getColumnType('purchases', 'amount'),
            ['decimal', 'numeric']
        );
        $this->assertSame(
            '125.4',
            (string) DB::table('sales')->where('id', 1)->value('outstanding_amount')
        );
    }

    public function test_empty_partial_banking_schema_is_rebuilt_safely(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('bank_transaction_allocations', function (Blueprint $table): void {
            $table->id();
        });

        $migration = require database_path(
            'migrations/2026_07_24_120000_create_banking_tables.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumns('bank_connections', [
            'provider',
            'environment',
            'status',
        ]));
        $this->assertTrue(Schema::hasColumns('bank_transaction_allocations', [
            'bank_transaction_id',
            'allocatable_type',
            'allocatable_id',
            'amount',
        ]));
        $this->assertTrue(Schema::hasTable('bank_audit_events'));
    }

    public function test_partial_banking_schema_with_data_is_never_dropped(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('bank_connections')->insert(['id' => 1]);

        $migration = require database_path(
            'migrations/2026_07_24_120000_create_banking_tables.php'
        );

        try {
            $migration->up();
            $this->fail('A populated partial schema must block automatic recovery.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('contains data', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('bank_connections')->count());
    }
}
