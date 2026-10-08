<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_url_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->string('slug')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_url_aliases');
    }
};
