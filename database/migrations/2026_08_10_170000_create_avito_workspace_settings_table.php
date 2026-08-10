<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_workspace_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('auth_mode', 20)->default('server');
            $table->unsignedBigInteger('default_account_id')->nullable();
            $table->foreignId('default_connection_id')
                ->nullable()
                ->constrained('avito_connections')
                ->nullOnDelete();
            $table->unsignedBigInteger('server_account_id')->nullable();
            $table->string('server_account_name', 255)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('server_account_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_workspace_settings');
    }
};
