<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_webhook_calls', function (Blueprint $table): void {
            $table->longText('raw_payload')->nullable()->change();
            $table->char('request_hash', 64)->nullable()->unique()->after('auth_valid');
            $table->string('status', 64)->default('verified')->index()->after('events_count');
            $table->string('safe_error_code', 64)->nullable()->index()->after('status');
            $table->string('safe_summary', 255)->nullable()->after('safe_error_code');
            $table->dateTime('verified_at')->nullable()->index()->after('safe_summary');
        });

        Schema::table('mailing_messages', function (Blueprint $table): void {
            $table->char('request_hash', 64)->nullable()->index()->after('provider_message_id');
            $table->char('response_hash', 64)->nullable()->index()->after('request_hash');
            $table->string('request_profile', 64)->default('legacy_manual')->index()->after('response_hash');
            $table->string('http_status_category', 16)->nullable()->index()->after('request_profile');
            $table->string('safe_request_id', 191)->nullable()->index()->after('http_status_category');
            $table->string('safe_error_code', 64)->nullable()->index()->after('safe_request_id');
            $table->string('safe_summary', 255)->nullable()->after('safe_error_code');
            $table->dateTime('ambiguous_acceptance_at')->nullable()->index()->after('safe_summary');
        });

        Schema::table('mailing_campaign_recipients', function (Blueprint $table): void {
            $table->string('safe_error_code', 64)->nullable()->index()->after('failure_reason');
            $table->string('safe_summary', 255)->nullable()->after('safe_error_code');
        });

        Schema::table('mailing_events', function (Blueprint $table): void {
            $table->json('payload')->nullable()->change();
            $table->foreignId('webhook_call_id')
                ->nullable()
                ->after('id')
                ->constrained('mailing_webhook_calls')
                ->nullOnDelete();
            $table->string('provider_event_id', 191)->nullable()->index()->after('event_fingerprint');
            $table->string('provider_message_id', 191)->nullable()->index()->after('unisender_job_id');
            $table->foreignId('mailing_message_id')
                ->nullable()
                ->after('provider_message_id')
                ->constrained('mailing_messages')
                ->nullOnDelete();
            $table->foreignId('sending_id')
                ->nullable()
                ->after('mailing_message_id')
                ->constrained('sendings')
                ->nullOnDelete();
            $table->foreignId('mail_message_id')
                ->nullable()
                ->after('sending_id')
                ->constrained('mail_messages')
                ->nullOnDelete();
            $table->string('normalized_event_type', 64)->nullable()->index()->after('event_name');
            $table->string('normalized_status', 64)->nullable()->index()->after('status');
            $table->dateTime('verified_at')->nullable()->index()->after('event_time');
            $table->dateTime('processed_at')->nullable()->index()->after('verified_at');
            $table->string('safe_error_code', 64)->nullable()->index()->after('processed_at');
            $table->string('safe_summary', 255)->nullable()->after('safe_error_code');
        });
    }

    public function down(): void
    {
        DB::table('mailing_events')->whereNull('payload')->update(['payload' => '{}']);

        Schema::table('mailing_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mail_message_id');
            $table->dropConstrainedForeignId('sending_id');
            $table->dropConstrainedForeignId('mailing_message_id');
            $table->dropConstrainedForeignId('webhook_call_id');
            $table->dropIndex(['provider_event_id']);
            $table->dropIndex(['provider_message_id']);
            $table->dropIndex(['normalized_event_type']);
            $table->dropIndex(['normalized_status']);
            $table->dropIndex(['verified_at']);
            $table->dropIndex(['processed_at']);
            $table->dropIndex(['safe_error_code']);
            $table->dropColumn([
                'provider_event_id',
                'provider_message_id',
                'normalized_event_type',
                'normalized_status',
                'verified_at',
                'processed_at',
                'safe_error_code',
                'safe_summary',
            ]);
            $table->json('payload')->nullable(false)->change();
        });

        Schema::table('mailing_messages', function (Blueprint $table): void {
            $table->dropIndex(['request_hash']);
            $table->dropIndex(['response_hash']);
            $table->dropIndex(['request_profile']);
            $table->dropIndex(['http_status_category']);
            $table->dropIndex(['safe_request_id']);
            $table->dropIndex(['safe_error_code']);
            $table->dropIndex(['ambiguous_acceptance_at']);
            $table->dropColumn([
                'request_hash',
                'response_hash',
                'request_profile',
                'http_status_category',
                'safe_request_id',
                'safe_error_code',
                'safe_summary',
                'ambiguous_acceptance_at',
            ]);
        });

        Schema::table('mailing_campaign_recipients', function (Blueprint $table): void {
            $table->dropIndex(['safe_error_code']);
            $table->dropColumn(['safe_error_code', 'safe_summary']);
        });

        DB::table('mailing_webhook_calls')->whereNull('raw_payload')->update(['raw_payload' => '{}']);

        Schema::table('mailing_webhook_calls', function (Blueprint $table): void {
            $table->dropUnique(['request_hash']);
            $table->dropIndex(['status']);
            $table->dropIndex(['safe_error_code']);
            $table->dropIndex(['verified_at']);
            $table->dropColumn([
                'request_hash',
                'status',
                'safe_error_code',
                'safe_summary',
                'verified_at',
            ]);
            $table->longText('raw_payload')->nullable(false)->change();
        });
    }
};
