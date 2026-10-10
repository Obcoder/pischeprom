<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_stock_alerts', function (Blueprint $table): void {
            $table->string('site_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('good_stock_alerts', function (Blueprint $table): void {
            $table->dropColumn('site_url');
        });
    }
};
