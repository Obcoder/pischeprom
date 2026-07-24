<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('measure_id')
                ->nullable()
                ->constrained('measures')
                ->nullOnDelete();
            $table->string('type', 40)->index();
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->date('moved_at')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(
                ['warehouse_id', 'good_id', 'measure_id'],
                'good_stock_movements_stock_index'
            );
        });

        Schema::create('good_stock_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('good_id')
                ->unique()
                ->constrained('goods')
                ->cascadeOnDelete();
            $table->boolean('is_in_stock')->default(false)->index();
            $table->timestamp('became_available_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('good_stock_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();
            $table->foreignId('max_chat_id')
                ->nullable()
                ->constrained('max_chats')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->char('start_token_hash', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['good_id', 'status'], 'good_stock_alerts_good_status_index');
            $table->index(['max_chat_id', 'status'], 'good_stock_alerts_chat_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_stock_alerts');
        Schema::dropIfExists('good_stock_availabilities');
        Schema::dropIfExists('good_stock_movements');
    }
};
