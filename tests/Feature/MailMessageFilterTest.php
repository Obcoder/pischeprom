<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailMessageFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_filter_respects_false_query_string_value(): void
    {
        $this->useInMemoryMailMessagesTable();

        Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

        MailMessage::query()->create($this->mailMessagePayload([
            'subject' => 'Today',
            'message_date' => now(),
        ]));

        MailMessage::query()->create($this->mailMessagePayload([
            'subject' => 'Yesterday',
            'message_date' => now()->subDay(),
        ]));

        $this->assertSame(2, MailMessage::query()->filter(['today' => false])->count());
        $this->assertSame(2, MailMessage::query()->filter(['today' => 'false'])->count());
        $this->assertSame(1, MailMessage::query()->filter(['today' => 'true'])->count());
    }

    private function useInMemoryMailMessagesTable(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('mailbox')->index();
            $table->string('folder')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('subject')->nullable();
            $table->timestamp('message_date')->nullable()->index();
            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->text('preview')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->longText('raw_headers')->nullable();
        });
    }

    private function mailMessagePayload(array $overrides = []): array
    {
        return array_merge([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'message_id' => fake()->uuid().'@example.test',
            'from_address' => 'client@example.test',
            'has_attachments' => false,
        ], $overrides);
    }
}
