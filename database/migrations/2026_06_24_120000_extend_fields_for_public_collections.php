<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            if (!Schema::hasColumn('fields', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('title');
            }

            if (!Schema::hasColumn('fields', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }

            if (!Schema::hasColumn('fields', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('description');
            }

            if (!Schema::hasColumn('fields', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(500)->after('is_published');
            }
        });

        if (!Schema::hasTable('field_good')) {
            Schema::create('field_good', function (Blueprint $table) {
                $table->id();
                $table->foreignId('field_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreignId('good_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
                $table->timestamps();

                $table->unique(['field_id', 'good_id']);
                $table->index(['good_id', 'field_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_good');

        Schema::table('fields', function (Blueprint $table) {
            foreach (['sort_order', 'is_published', 'description', 'slug'] as $column) {
                if (Schema::hasColumn('fields', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
