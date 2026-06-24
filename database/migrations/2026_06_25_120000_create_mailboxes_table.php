<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mailboxes')) {
            return;
        }

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->string('name')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable()->default(993);
            $table->string('imap_encryption', 32)->nullable()->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_inbox')->nullable()->default('INBOX');
            $table->string('imap_sent')->nullable()->default('Sent');
            $table->json('imap_folders')->nullable();

            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable()->default(465);
            $table->string('smtp_encryption', 32)->nullable()->default('ssl');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->unsignedSmallInteger('smtp_timeout')->default(30);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
