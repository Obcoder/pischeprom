<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yandex_direct_ad_group_id')->constrained('yandex_direct_ad_groups')->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->string('external_keyword_id')->nullable()->index();
            $table->text('phrase');
            $table->decimal('bid', 12, 2)->nullable();
            $table->decimal('context_bid', 12, 2)->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_negative')->default(false)->index();
            $table->timestamps();

            $table->index(['good_id', 'is_negative'], 'yd_keywords_good_negative_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_keywords');
    }
};
