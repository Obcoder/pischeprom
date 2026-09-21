<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Inline references let SQLite add columns without rebuilding populated tables.
            // A rebuild inside a transaction can cascade-delete child rows: SQLite ignores
            // PRAGMA foreign_keys=OFF while a transaction is already active.
            DB::statement('ALTER TABLE "order_items" ADD COLUMN "measure_id" integer REFERENCES "measures" ("id") ON DELETE RESTRICT');
            foreach ($this->sqliteOrderColumns() as $column => $definition) {
                DB::statement('ALTER TABLE "orders" ADD COLUMN "'.$column.'" '.$definition);
            }
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('prepared_at');
                $table->index('shipped_at');
                $table->unique('shipped_sale_id');
            });

            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('measure_id')->nullable()->constrained('measures')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('fulfillment_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable()->index();
            $table->string('prepared_fingerprint', 64)->nullable();
            $table->timestamp('preparation_invalidated_at')->nullable();
            $table->foreignId('shipped_sale_id')->nullable()->unique()->constrained('sales')->restrictOnDelete();
            $table->foreignId('shipped_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (DB::table('orders')->whereNotNull('shipped_sale_id')->exists()) {
            throw new RuntimeException('Откат запрещён: существуют отгруженные заказы.');
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['prepared_at']);
                $table->dropIndex(['shipped_at']);
                $table->dropUnique(['shipped_sale_id']);
            });
            foreach (array_keys($this->sqliteOrderColumns()) as $column) {
                DB::statement('ALTER TABLE "orders" DROP COLUMN "'.$column.'"');
            }
            DB::statement('ALTER TABLE "order_items" DROP COLUMN "measure_id"');

            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fulfillment_warehouse_id');
            $table->dropConstrainedForeignId('prepared_by_user_id');
            $table->dropConstrainedForeignId('shipped_by_user_id');
            $table->dropForeign(['shipped_sale_id']);
            $table->dropUnique(['shipped_sale_id']);
            $table->dropColumn('shipped_sale_id');
            $table->dropIndex(['prepared_at']);
            $table->dropIndex(['shipped_at']);
            $table->dropColumn(['prepared_at', 'prepared_fingerprint', 'preparation_invalidated_at', 'shipped_at']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('measure_id'));
    }

    private function sqliteOrderColumns(): array
    {
        return [
            'fulfillment_warehouse_id' => 'integer REFERENCES "warehouses" ("id") ON DELETE RESTRICT',
            'prepared_by_user_id' => 'integer REFERENCES "users" ("id") ON DELETE SET NULL',
            'prepared_at' => 'datetime',
            'prepared_fingerprint' => 'varchar(64)',
            'preparation_invalidated_at' => 'datetime',
            'shipped_sale_id' => 'integer REFERENCES "sales" ("id") ON DELETE RESTRICT',
            'shipped_by_user_id' => 'integer REFERENCES "users" ("id") ON DELETE SET NULL',
            'shipped_at' => 'datetime',
        ];
    }
};
