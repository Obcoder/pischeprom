<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_ai_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_account_id')->constrained('yandex_accounts')->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('type')->index();
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->json('expected_impact')->nullable();
            $table->string('risk_level')->default('medium')->index();
            $table->string('status')->default('pending')->index();
            $table->json('reason')->nullable();
            $table->json('signals')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['yandex_account_id', 'status', 'type'], 'yd_ai_decisions_account_status_type_idx');
            $table->index(['good_id', 'type', 'status'], 'yd_ai_decisions_good_type_status_idx');
            $table->index(['created_at', 'status'], 'yd_ai_decisions_created_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_ai_decisions');
    }
};
