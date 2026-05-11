<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_seos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->unique()
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->string('h1')->nullable();
            $table->string('slug_override')->nullable();
            $table->string('canonical_url')->nullable();

            $table->string('robots')->default('index,follow');

            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();

            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();

            $table->text('short_seo_text')->nullable();
            $table->longText('seo_text')->nullable();

            $table->json('semantic_core')->nullable();
            $table->json('keywords')->nullable();
            $table->json('search_queries')->nullable();
            $table->json('structured_data')->nullable();

            $table->string('focus_keyword')->nullable();
            $table->string('breadcrumbs_title')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_seos');
    }
};
