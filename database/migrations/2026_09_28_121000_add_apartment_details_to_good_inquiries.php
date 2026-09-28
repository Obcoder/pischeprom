<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_inquiries', function (Blueprint $table): void {
            $table->string('delivery_apartment_number', 50)->nullable();
            $table->string('delivery_apartment_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('good_inquiries', function (Blueprint $table): void {
            $table->dropColumn(['delivery_apartment_number', 'delivery_apartment_type']);
        });
    }
};
