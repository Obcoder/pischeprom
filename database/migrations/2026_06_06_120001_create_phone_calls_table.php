<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_calls', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('provider', 32)->default('beeline')->index();
            $table->string('provider_call_id')->nullable();
            $table->string('event_type', 32)->nullable()->index();
            $table->string('direction', 16)->default('unknown')->index();
            $table->string('status', 32)->nullable()->index();
            $table->char('client_phone', 16)->nullable()->index();
            $table->string('employee_user')->nullable()->index();
            $table->string('employee_extension', 32)->nullable()->index();
            $table->char('employee_phone', 16)->nullable();
            $table->string('group_name')->nullable();
            $table->char('diversion_phone', 16)->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('wait_seconds')->nullable();
            $table->text('recording_url')->nullable();
            $table->foreignId('telephone_id')->nullable()->constrained('telephones')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete()->cascadeOnUpdate();
            $table->json('raw_payload')->nullable();

            $table->unique(['provider', 'provider_call_id']);
            $table->index(['provider', 'started_at']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_calls');
    }
};
