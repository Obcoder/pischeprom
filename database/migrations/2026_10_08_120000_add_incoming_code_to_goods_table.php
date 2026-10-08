<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table): void {
            $table->string('incoming_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table): void {
            $table->dropColumn('incoming_code');
        });
    }
};
