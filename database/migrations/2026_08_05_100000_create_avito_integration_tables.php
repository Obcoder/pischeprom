<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('auth_mode', 32)->default('authorization_code');
            $table->string('external_user_id')->nullable()->index();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_capability_settings', function (Blueprint $table) {
            $table->id();
            $table->string('capability_id', 120)->unique();
            $table->boolean('enabled')->default(true)->index();
            $table->text('notes')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_api_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_connection_id')->nullable()->constrained('avito_connections')->nullOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('capability_id', 120)->index();
            $table->string('method', 10);
            $table->text('endpoint');
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('request_meta')->nullable();
            $table->longText('response_meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('deduplication_key', 64)->unique();
            $table->string('external_event_id')->nullable()->index();
            $table->string('event_type')->nullable()->index();
            $table->longText('payload');
            $table->string('status', 32)->default('received')->index();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_webhook_events');
        Schema::dropIfExists('avito_api_calls');
        Schema::dropIfExists('avito_capability_settings');
        Schema::dropIfExists('avito_connections');
    }
};
