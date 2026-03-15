<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_search_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')
                ->constrained('product_search_requests')
                ->cascadeOnDelete();

            $table->unsignedInteger('position');
            $table->string('title')->nullable();
            $table->text('url');
            $table->string('domain')->nullable();
            $table->longText('snippet')->nullable();

            $table->timestamps();

            $table->unique(['request_id', 'position']);
            $table->index(['request_id']);
            $table->index(['domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_results');
    }
};
