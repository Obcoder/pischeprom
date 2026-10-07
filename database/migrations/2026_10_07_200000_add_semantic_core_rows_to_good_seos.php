<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_seos', function (Blueprint $table): void {
            $table->json('semantic_core_rows')->nullable()->after('semantic_core');
        });
    }

    public function down(): void
    {
        Schema::table('good_seos', function (Blueprint $table): void {
            $table->dropColumn('semantic_core_rows');
        });
    }
};
