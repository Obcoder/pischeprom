<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('source')->default('manual')->index();
            $table->string('status', 32)->default('open')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->char('client_phone', 16)->nullable()->index();
            $table->foreignId('telephone_id')->nullable()->constrained('telephones')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('first_phone_call_id')->nullable();
            $table->unsignedBigInteger('last_phone_call_id')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();

            $table->index(['source', 'status']);
            $table->index(['telephone_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
