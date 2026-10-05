<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_banners', function (Blueprint $table): void {
            $table->unsignedTinyInteger('slot_number')->nullable();
            $table->string('content_mode', 16)->default('image');
            $table->string('image_fit', 16)->default('contain');
            $table->string('image_position', 32)->default('center center');
            $table->string('mobile_image_fit', 16)->default('contain');
            $table->string('mobile_image_position', 32)->default('center center');
            $table->string('text_align', 16)->default('left');
            $table->string('vertical_align', 16)->default('center');
            $table->string('alt_text')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->index(['slot_number', 'is_published', 'sort_order'], 'home_banners_slot_publication_index');
        });

        // Keep every existing campaign and asset. Only the first six receive a slot.
        $existing = DB::table('home_banners')->orderBy('sort_order')->orderBy('id')->limit(6)->pluck('id');
        foreach ($existing as $index => $id) {
            DB::table('home_banners')->where('id', $id)->update(['slot_number' => $index + 1]);
        }

        Schema::create('home_banner_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('desktop_height')->default(96);
            $table->unsignedTinyInteger('gap')->default(8);
            $table->boolean('mobile_enabled')->default(true);
            $table->string('mobile_layout', 16)->default('scroll');
            $table->unsignedSmallInteger('mobile_height')->default(96);
            $table->unsignedTinyInteger('mobile_columns')->default(2);
            $table->boolean('mobile_hide_empty')->default(true);
            $table->json('mobile_order');
            $table->timestamps();
        });

        DB::table('home_banner_settings')->insert([
            'id' => 1,
            'mobile_order' => json_encode([1, 2, 3, 4, 5, 6]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banner_settings');

        Schema::table('home_banners', function (Blueprint $table): void {
            $table->dropIndex('home_banners_slot_publication_index');
            $table->dropColumn([
                'slot_number', 'content_mode', 'image_fit', 'image_position',
                'mobile_image_fit', 'mobile_image_position', 'text_align',
                'vertical_align', 'alt_text', 'open_in_new_tab',
            ]);
        });
    }
};
