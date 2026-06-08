<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_industry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained('industries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['good_id', 'industry_id']);
            $table->index(['industry_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_industry');
    }
};
