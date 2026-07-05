<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_commodity', function (Blueprint $table) {
            if (! Schema::hasColumn('check_commodity', 'expense_article_id')) {
                $table->foreignId('expense_article_id')
                    ->nullable()
                    ->after('measure_id')
                    ->constrained('expense_articles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('check_commodity', function (Blueprint $table) {
            if (Schema::hasColumn('check_commodity', 'expense_article_id')) {
                $table->dropConstrainedForeignId('expense_article_id');
            }
        });
    }
};
