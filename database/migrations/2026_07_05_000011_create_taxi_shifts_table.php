<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxi_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('revenue_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_shifts');
    }
};
