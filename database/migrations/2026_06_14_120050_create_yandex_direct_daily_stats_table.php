<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_account_id')->constrained('yandex_accounts')->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('yandex_direct_campaigns')->nullOnDelete();
            $table->foreignId('ad_group_id')->nullable()->constrained('yandex_direct_ad_groups')->nullOnDelete();
            $table->foreignId('ad_id')->nullable()->constrained('yandex_direct_ads')->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('yandex_direct_keywords')->nullOnDelete();
            $table->date('date')->index();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('cost', 14, 4)->default(0);
            $table->decimal('avg_cpc', 14, 4)->nullable();
            $table->decimal('ctr', 10, 4)->nullable();
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('cost_per_conversion', 14, 4)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(
                ['date', 'yandex_account_id', 'campaign_id', 'ad_group_id', 'ad_id', 'keyword_id'],
                'yd_daily_stats_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_daily_stats');
    }
};
