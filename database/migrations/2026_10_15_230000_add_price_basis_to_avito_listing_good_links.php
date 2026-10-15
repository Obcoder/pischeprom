<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_listing_good_links', function (Blueprint $table): void {
            $table->json('price_basis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('avito_listing_good_links', fn (Blueprint $table) => $table->dropColumn('price_basis'));
    }
};
