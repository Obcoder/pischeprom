<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_account_id')->constrained('yandex_accounts')->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('external_campaign_id')->nullable()->index();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('draft')->index();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->json('region_ids')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['yandex_account_id', 'good_id', 'status'], 'yd_campaigns_account_good_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_campaigns');
    }
};
