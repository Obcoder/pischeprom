<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')
                ->constrained('entities')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role', 32)
                ->default('owner');

            $table->string('status', 32)
                ->default('active');

            $table->boolean('is_primary')
                ->default(true);

            $table->timestamps();

            $table->unique(['entity_id', 'user_id']);
            $table->index(['user_id', 'is_primary']);
            $table->index(['entity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_user');
    }
};
