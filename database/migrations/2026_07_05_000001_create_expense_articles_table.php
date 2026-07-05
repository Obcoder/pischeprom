<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_articles')) {
            return;
        }

        Schema::create('expense_articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('color', 24)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(500);
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_articles');
    }
};
