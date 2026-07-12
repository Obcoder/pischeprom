<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->string('customer_account_type', 32)->nullable();
            $table->string('customer_city_name')->nullable();
            $table->string('customer_entity_name')->nullable();
            $table->decimal('total_amount', 16, 4)->default(0);
            $table->decimal('total_weight', 16, 4)->nullable();
            $table->string('currency_code', 8)->default('RUB');
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
