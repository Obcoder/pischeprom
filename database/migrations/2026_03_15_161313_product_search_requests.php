<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_search_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('engine')->default('yandex');
            $table->string('query');

            $table->string('status')->default('queued'); // queued, processing, done, failed
            $table->unsignedInteger('results_count')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('searched_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'engine']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_requests');
    }
};
