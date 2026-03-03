<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sendings', function (Blueprint $table) {

            // HTML / текст письма
            $table->longText('html')->nullable()->after('subject');
            $table->longText('text')->nullable()->after('html');

            // Провайдер
            $table->string('provider')->default('unisender')->after('text');
            $table->string('provider_message_id')->nullable()->after('provider');

            // Трекинг
            $table->uuid('tracking_token')->unique()->after('provider_message_id');

            $table->string('status')->default('draft')->after('tracking_token');
            // draft | queued | sent | failed

            $table->timestamp('sent_at')->nullable()->after('status');

            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->unsignedInteger('opens_count')->default(0)->after('opened_at');

            $table->unsignedInteger('clicks_count')->default(0)->after('opens_count');
            $table->timestamp('last_clicked_at')->nullable()->after('clicks_count');

            $table->text('error')->nullable()->after('last_clicked_at');

            $table->index(['email_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sendings', function (Blueprint $table) {
            $table->dropColumn([
                                   'html',
                                   'text',
                                   'provider',
                                   'provider_message_id',
                                   'tracking_token',
                                   'status',
                                   'sent_at',
                                   'opened_at',
                                   'opens_count',
                                   'clicks_count',
                                   'last_clicked_at',
                                   'error',
                               ]);
        });
    }
};
