<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_direct_ad_group_id')->constrained('yandex_direct_ad_groups')->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->foreignId('good_seo_id')->nullable()->constrained('good_seos')->nullOnDelete();
            $table->string('external_ad_id')->nullable()->index();
            $table->string('title_1');
            $table->string('title_2')->nullable();
            $table->text('text');
            $table->text('href');
            $table->text('utm_template')->nullable();
            $table->text('image_url')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('moderation_status')->nullable()->index();
            $table->json('validation_errors')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['good_id', 'status'], 'yd_ads_good_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_ads');
    }
};
