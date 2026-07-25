<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->text('wiki')->nullable()->change();
            $table->text('wiki_thumbnail')->nullable()->change();
            $table->text('yandexmapsgeo')->nullable()->change();
            $table->text('twogis')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->string('wiki')->nullable()->change();
            $table->string('wiki_thumbnail')->nullable()->change();
            $table->string('yandexmapsgeo')->nullable()->change();
            $table->string('twogis')->nullable()->change();
        });
    }
};
