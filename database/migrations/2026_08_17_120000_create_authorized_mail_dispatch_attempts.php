<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorized_mail_dispatch_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('route_name', 96);
            $table->char('idempotency_key_hash', 64)->unique();
            $table->char('request_hash', 64);
            $table->unsignedTinyInteger('recipient_count');
            $table->unsignedTinyInteger('attachment_count')->default(0);
            $table->string('status', 24)->default('claimed');
            $table->string('safe_error_code', 64)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'authorized_mail_actor_status_idx');
            $table->index(['unit_id', 'created_at'], 'authorized_mail_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_mail_dispatch_attempts');
    }
};
