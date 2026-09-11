<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_stock_requests', function (Blueprint $table): void {
            $table->uuid('request_id')->primary();
            $table->string('action', 40);
            $table->char('payload_hash', 64);
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_stock_requests');
    }
};
