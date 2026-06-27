<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('eyebrow')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('mobile_image_url', 2048)->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->foreignId('good_id')->nullable()->constrained('goods')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('size', 32)->default('wide');
            $table->boolean('is_published')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->boolean('show_on_mobile')->default(true);
            $table->unsignedInteger('sort_order')->default(500);
            $table->string('background_color', 32)->nullable();
            $table->string('text_color', 32)->nullable();
            $table->string('accent_color', 32)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });

        DB::table('home_banners')->insert([
            'title' => 'Лецитин подсолнечный',
            'eyebrow' => 'Баннер',
            'subtitle' => 'Для эмульгации пищевых продуктов',
            'description' => 'Готовая промо-карточка для быстрого перехода к товарам и запросу поставки.',
            'image_url' => 'https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png',
            'cta_label' => 'Смотреть товары',
            'cta_url' => '/g?search=%D0%9B%D0%B5%D1%86%D0%B8%D1%82%D0%B8%D0%BD',
            'size' => 'wide',
            'is_published' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
            'sort_order' => 100,
            'background_color' => '#fff7df',
            'text_color' => '#2b2118',
            'accent_color' => '#f2aa00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
