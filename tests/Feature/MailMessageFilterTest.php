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

    public function test_date_range_includes_both_days_and_excludes_the_following_midnight(): void
    {
        $this->useInMemoryMailMessagesTable();

        foreach ([
            '2026-09-19 23:59:59',
            '2026-09-20 00:00:00',
            '2026-09-21 23:59:59',
            '2026-09-22 00:00:00',
        ] as $date) {
            MailMessage::query()->create($this->mailMessagePayload(['message_date' => $date]));
        }

        $this->assertSame(2, MailMessage::query()->filter([
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-21',
        ])->count());
        $this->assertSame(3, MailMessage::query()->filter(['date_from' => '2026-09-20'])->count());
        $this->assertSame(3, MailMessage::query()->filter(['date_to' => '2026-09-21'])->count());
        $this->assertSame(1, MailMessage::query()->filter([
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-21',
        ])->count());
    }

    public function test_explicit_dates_take_precedence_over_legacy_today_filter(): void
    {
        $this->useInMemoryMailMessagesTable();
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00'));

        MailMessage::query()->create($this->mailMessagePayload(['message_date' => now()]));
        MailMessage::query()->create($this->mailMessagePayload(['message_date' => now()->subDay()]));

        $message = MailMessage::query()->filter([
            'today' => 'true',
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-21',
        ])->sole();

        $this->assertSame('2026-09-21', $message->message_date->toDateString());
    }

    public function test_invalid_and_reversed_dates_are_rejected_before_querying_messages(): void
    {
        foreach ([
            ['date_from' => 'yesterday'],
            ['date_from' => '2026-02-30'],
            ['date_to' => '2026-09-22 12:00:00'],
            ['date_from' => '2026-09-22', 'date_to' => '2026-09-21'],
        ] as $filters) {
            $this->getJson('/api/mail-messages?'.http_build_query(['filters' => $filters]))
                ->assertUnprocessable();
        }
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
            $table->softDeletes();
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
