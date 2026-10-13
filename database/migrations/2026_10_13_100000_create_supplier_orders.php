<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('number', 60)->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('delivery_date')->nullable()->index();
            $table->string('subject')->default('');
            $table->text('body')->nullable();
            $table->string('mailbox')->nullable();
            $table->json('to');
            $table->json('cc');
            $table->boolean('include_prices')->default(true);
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('create_key');
            $table->string('create_payload_hash', 64);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('mail_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $table->uuid('sending_key')->nullable();
            $table->foreignId('sending_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sending_started_at')->nullable();
            $table->string('send_error_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['created_by', 'create_key']);
            $table->index(['unit_id', 'status']);
        });
        Schema::create('supplier_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained('supplier_orders')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('name');
            $table->foreignId('measure_id')->nullable()->constrained('measures')->nullOnDelete();
            $table->string('unit_label');
            $table->decimal('kilograms_per_unit', 18, 6)->nullable();
            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_price', 16, 4)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('currency_code', 16)->nullable();
            $table->text('note')->nullable();
            $table->json('source_order_item_ids');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->json('quotation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['supplier_order_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_items');
        Schema::dropIfExists('supplier_orders');
    }
};
