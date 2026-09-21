<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->string('request_hash', 64);
            $table->string('number', 40)->unique();
            $table->foreignId('good_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->string('good_name');
            $table->string('good_url', 2048);
            $table->unsignedInteger('quantity');
            $table->decimal('package_weight', 14, 4)->nullable();
            $table->decimal('listed_price', 18, 4)->nullable();
            $table->decimal('proposed_price', 18, 4)->nullable();
            $table->string('price_unit', 16);
            $table->string('currency_code', 8);
            $table->string('customer_name', 160);
            $table->string('customer_email', 254);
            $table->string('customer_phone', 64)->nullable();
            $table->string('company')->nullable();
            $table->string('delivery_city', 160)->nullable();
            $table->string('delivery_address', 1000)->nullable();
            $table->string('preferred_contact', 16)->default('email');
            $table->string('max_contact', 255)->nullable();
            $table->string('bargain_scenario', 16)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('consent_at');
            $table->timestamp('email_notified_at')->nullable();
            $table->json('max_delivered_to')->nullable();
            $table->unsignedInteger('notification_attempts')->default(0);
            $table->timestamp('next_notification_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_inquiries');
    }
};
