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
            $this->replaceSqliteTotal(true);

            return;
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('total_amount', 16, 4)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->replaceSqliteTotal(false);

            return;
        }
        DB::table('orders')->whereNull('total_amount')->update(['total_amount' => 0]);
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('total_amount', 16, 4)->nullable(false)->default(0)->change();
        });
    }

    private function replaceSqliteTotal(bool $nullable): void
    {
        // SQLite table rebuilds can cascade-delete child rows inside a transaction.
        // Replace this unindexed scalar column while preserving the original table and references.
        DB::statement('ALTER TABLE "orders" ADD COLUMN "currency_total_amount" numeric '.($nullable ? '' : 'NOT NULL ').'DEFAULT 0');
        DB::statement('UPDATE "orders" SET "currency_total_amount" = '.($nullable ? '"total_amount"' : 'COALESCE("total_amount", 0)'));
        DB::statement('ALTER TABLE "orders" DROP COLUMN "total_amount"');
        DB::statement('ALTER TABLE "orders" RENAME COLUMN "currency_total_amount" TO "total_amount"');
    }
};
