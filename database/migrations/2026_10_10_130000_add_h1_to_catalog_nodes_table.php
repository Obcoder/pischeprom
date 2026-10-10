<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_nodes', function (Blueprint $table): void {
            $table->string('h1')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_nodes', function (Blueprint $table): void {
            $table->dropColumn('h1');
        });
    }
};
