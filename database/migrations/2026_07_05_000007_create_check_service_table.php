<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_service', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('check_id')
                ->constrained('checks')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['check_id', 'service_id'], 'check_service_check_id_service_id_index');
            $table->double('quantity')->default(1);
            $table->foreignId('measure_id')
                ->nullable()
                ->constrained('measures')
                ->nullOnDelete();
            $table->foreignId('expense_article_id')
                ->nullable()
                ->constrained('expense_articles')
                ->nullOnDelete();
            $table->double('price')->default(0);
            $table->double('total_price')->storedAs('quantity * price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_service');
    }
};
