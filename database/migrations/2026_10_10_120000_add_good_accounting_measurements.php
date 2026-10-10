<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing goods are deliberately left unconfigured: package size alone
        // cannot tell us whether the supplier counts kilograms, pieces or boxes.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE "goods" ADD COLUMN "measure_id" integer REFERENCES "measures" ("id") ON DELETE RESTRICT');
            DB::statement('ALTER TABLE "goods" ADD COLUMN "unit_weight_kg" numeric');
        } else {
            Schema::table('goods', function (Blueprint $table): void {
                $table->foreignId('measure_id')->nullable()->constrained('measures')->restrictOnDelete();
                $table->decimal('unit_weight_kg', 18, 6)->nullable();
            });
        }

        foreach (['кг', 'г', 'т', 'шт.', 'коробка', 'упаковка', 'л'] as $name) {
            if (! DB::table('measures')->where('name', $name)->exists()) {
                DB::table('measures')->insert(['name' => $name]);
            }
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE "good_inquiries" ADD COLUMN "measure_id" integer REFERENCES "measures" ("id") ON DELETE RESTRICT');
            DB::statement('ALTER TABLE "good_inquiries" ADD COLUMN "unit_label" varchar(255)');
            DB::statement('ALTER TABLE "good_inquiries" ADD COLUMN "unit_weight_kg" numeric');
        } else {
            Schema::table('good_inquiries', function (Blueprint $table): void {
                $table->decimal('quantity', 12, 3)->change();
                $table->foreignId('measure_id')->nullable()->constrained('measures')->restrictOnDelete();
                $table->string('unit_label')->nullable();
                $table->decimal('unit_weight_kg', 18, 6)->nullable();
            });
        }
        // Legacy inquiries always used packages. Keep their original meaning.
        DB::table('good_inquiries')->update(['unit_label' => 'упак.']);
        DB::table('good_inquiries')->update(['unit_weight_kg' => DB::raw('package_weight')]);

        // Grams and fractional quantities require sub-gram mass precision.
        // SQLite already stores the full numeric precision; rebuilding parent
        // tables to change their declaration can cascade-delete child records.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('denominator', 18, 6)->nullable()->change();
                $table->decimal('line_weight', 20, 6)->nullable()->change();
            });
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('total_weight', 20, 6)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['good_inquiries' => ['measure_id', 'unit_label', 'unit_weight_kg'], 'goods' => ['measure_id', 'unit_weight_kg']] as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement('ALTER TABLE "'.$table.'" DROP COLUMN "'.$column.'"');
                }
            }

            return;
        }
        Schema::table('good_inquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('measure_id');
            $table->dropColumn(['unit_label', 'unit_weight_kg']);
        });
        // Keep decimal quantities on rollback; truncating fractions loses data.
        Schema::table('goods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('measure_id');
            $table->dropColumn('unit_weight_kg');
        });
    }
};
