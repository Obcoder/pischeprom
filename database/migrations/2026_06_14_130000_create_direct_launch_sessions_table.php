<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_launch_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->foreignId('yandex_account_id')->nullable()->constrained('yandex_accounts')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('step')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->json('external_ids')->nullable();
            $table->json('rollback_payload')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['good_id', 'yandex_account_id', 'status'], 'direct_launch_good_account_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_launch_sessions');
    }
};
