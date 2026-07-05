<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            if (! Schema::hasColumn('commodities', 'expense_article_id')) {
                $table->foreignId('expense_article_id')
                    ->nullable()
                    ->after('ava')
                    ->constrained('expense_articles')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('commodities', 'project_id')) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->after('expense_article_id')
                    ->constrained('projects')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            if (Schema::hasColumn('commodities', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }

            if (Schema::hasColumn('commodities', 'expense_article_id')) {
                $table->dropConstrainedForeignId('expense_article_id');
            }
        });
    }
};
