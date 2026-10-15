<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Historical terms are unknown; do not invent currency, tax or offer dates.
            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('price_includes_vat')->nullable();
            $table->decimal('vat_rate', 7, 4)->nullable();
            $table->decimal('minimum_quantity', 16, 4)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->index(['unit_id', 'good_id', 'quoted_at'], 'quotations_supplier_good_date');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex('quotations_supplier_good_date');
            $table->dropColumn(['quoted_at', 'valid_until', 'price_includes_vat', 'vat_rate', 'minimum_quantity', 'lead_time_days', 'notes']);
        });
    }
};
