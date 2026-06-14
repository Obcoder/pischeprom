<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_account_id')->nullable()->constrained('yandex_accounts')->nullOnDelete();
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('status')->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['yandex_account_id', 'action', 'status'], 'yd_logs_account_action_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_sync_logs');
    }
};
