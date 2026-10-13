<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained('units')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('priority', 24)->default('normal');
            $table->boolean('is_watched')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('review_interval_days')->default(30);
            $table->date('next_review_on')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('create_key')->nullable();
            $table->string('create_payload_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['created_by', 'create_key']);
            $table->index(['is_watched', 'status', 'next_review_on'], 'supplier_profile_review_idx');
            $table->index(['owner_user_id', 'status']);
        });
        Schema::create('supplier_profile_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_profile_id')->constrained('supplier_profiles')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('type', 30);
            $table->text('body');
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['supplier_profile_id', 'id'], 'supplier_profile_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_profile_events');
        Schema::dropIfExists('supplier_profiles');
    }
};
