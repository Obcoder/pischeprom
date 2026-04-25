<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('wiki_title')->nullable();
            $table->text('wiki_summary')->nullable();
            $table->string('wiki_thumbnail')->nullable();
            $table->unsignedBigInteger('wiki_page_id')->nullable()->index();
            $table->string('wiki_lang', 8)->default('ru');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['wiki_page_id']);

            $table->dropColumn([
                                   'wiki_title',
                                   'wiki_summary',
                                   'wiki_thumbnail',
                                   'wiki_page_id',
                                   'wiki_lang',
                               ]);
        });
    }
};
