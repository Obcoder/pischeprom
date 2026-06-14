<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_ad_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_direct_campaign_id')->constrained('yandex_direct_campaigns')->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('external_ad_group_id')->nullable()->index();
            $table->string('name');
            $table->string('status')->default('draft')->index();
            $table->json('region_ids')->nullable();
            $table->text('minus_keywords')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['yandex_direct_campaign_id', 'good_id', 'status'], 'yd_groups_campaign_good_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_ad_groups');
    }
};
