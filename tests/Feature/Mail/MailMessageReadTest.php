<?php

namespace Tests\Feature\Mail;

use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Models\MailMessage;
use App\Models\User;
use App\Services\Mail\IncomingMailMaxNotificationDispatcher;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Webklex\PHPIMAP\Message;

class MailMessageReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('mailbox');
            $table->string('folder');
            $table->string('direction');
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('body_loaded_at')->nullable();
        });
        (require database_path('migrations/2026_09_22_100000_add_is_seen_to_mail_messages_table.php'))->up();

        foreach (['mail_message_attachments', 'mail_message_notes', 'leads'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
                $table->foreignId('mail_message_id');
            });
        }
    }

    public function test_guest_cannot_mark_server_mail_as_read(): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('markRead');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->postJson($this->url($message))->assertUnauthorized();
        $this->assertFalse($message->fresh()->is_seen);
    }

    public function test_unverified_user_cannot_mark_server_mail_as_read(): void
    {
        $message = $this->message();
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('markRead');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->user(verified: false))
            ->postJson($this->url($message))->assertForbidden();
        $this->assertFalse($message->fresh()->is_seen);
    }

    public function test_verified_user_marks_remote_message_before_local_state_changes(): void
    {
        $message = $this->message();
        $remote = Mockery::mock(Message::class);
        $remote->shouldReceive('setFlag')->once()->with('Seen')->andReturnUsing(function () use ($message): bool {
            $this->assertFalse($message->fresh()->is_seen);

            return true;
        });
        $remote->shouldReceive('hasFlag')->once()->with('Seen')->andReturn(true);
        $this->bindImapService($remote);

        $this->actingAs($this->user())->postJson($this->url($message))
            ->assertOk()->assertExactJson(['id' => $message->id, 'is_seen' => true]);
        $this->assertTrue($message->fresh()->is_seen);
    }

    #[DataProvider('rejectedFlagResults')]
    public function test_server_rejection_never_marks_local_message_as_read(bool $accepted, bool $seen): void
    {
        $message = $this->message();
        $remote = Mockery::mock(Message::class);
        $remote->shouldReceive('setFlag')->once()->with('Seen')->andReturn($accepted);
        if ($accepted) {
            $remote->shouldReceive('hasFlag')->once()->with('Seen')->andReturn($seen);
        }
        $this->bindImapService($remote);

        $this->actingAs($this->user())->postJson($this->url($message))
            ->assertStatus(502)->assertJsonMissing(['is_seen' => true]);
        $this->assertFalse($message->fresh()->is_seen);
    }

    public static function rejectedFlagResults(): array
    {
        return [
            'store rejected' => [false, false],
            'server did not return Seen' => [true, false],
        ];
    }

    public function test_remote_failure_is_reported_without_exposing_provider_details(): void
    {
        $message = $this->message();
        $remote = Mockery::mock(Message::class);
        $remote->shouldReceive('setFlag')->once()->with('Seen')
            ->andThrow(new RuntimeException('IMAP secret provider details'));
        $this->bindImapService($remote);

        $response = $this->actingAs($this->user())->postJson($this->url($message));

        $response->assertStatus(502);
        $this->assertStringNotContainsString('secret provider details', $response->getContent());
        $this->assertFalse($message->fresh()->is_seen);
    }

    public function test_missing_remote_message_is_not_reported_as_read(): void
    {
        $message = $this->message();
        $this->bindImapService(null);

        $this->actingAs($this->user())->postJson($this->url($message))->assertStatus(502);
        $this->assertFalse($message->fresh()->is_seen);
    }

    public function test_local_only_message_cannot_be_marked_on_server(): void
    {
        $message = $this->message(['imap_uid' => null]);
        $service = Mockery::mock(YandexMailboxService::class);
        $service->shouldNotReceive('markRead');
        $this->app->instance(YandexMailboxService::class, $service);

        $this->actingAs($this->user())->postJson($this->url($message))->assertUnprocessable();
        $this->assertFalse($message->fresh()->is_seen);
    }

    public function test_loading_body_keeps_remote_mail_unread_and_refreshes_local_server_status(): void
    {
        $message = $this->message(['is_seen' => null]);
        $remote = Mockery::mock(Message::class);
        $remote->shouldNotReceive('setFlag');
        $remote->shouldReceive('getHTMLBody')->once()->andReturn('<p>Message body</p>');
        $remote->shouldReceive('getTextBody')->once()->andReturn('Message body');
        $remote->shouldReceive('hasFlag')->once()->with('Seen')->andReturn(false);
        $service = $this->bindImapService($remote, fetchBody: true);

        $loaded = $service->loadBody($message, force: true, includeAttachmentList: false);

        $this->assertNull($loaded->getAttribute('mail_sync_error'));
        $this->assertSame('Message body', $loaded->text);
        $this->assertFalse($loaded->is_seen);
        $this->assertFalse($message->fresh()->is_seen);
    }

    private function bindImapService(?Message $remote, bool $fetchBody = false): YandexMailboxService
    {
        $registry = Mockery::mock(MailboxRegistry::class);
        $registry->shouldReceive('find')->once()->with('office@example.test')
            ->andReturn(['address' => 'office@example.test']);
        $client = Mockery::mock();
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('disconnect')->once();
        $query = Mockery::mock();
        $query->shouldReceive('leaveUnread')->once()->andReturnSelf();
        $query->shouldReceive('setFetchBody')->once()->with($fetchBody)->andReturnSelf();
        if (! $fetchBody) {
            $query->shouldReceive('setFetchFlags')->once()->with(true)->andReturnSelf();
        }
        $query->shouldReceive('getMessageByUid')->once()->with(42)->andReturn($remote);
        $folder = Mockery::mock();
        $folder->shouldReceive('query')->once()->andReturn($query);

        $service = Mockery::mock(YandexMailboxService::class, [
            $registry,
            Mockery::mock(IncomingMailMaxNotificationDispatcher::class),
            Mockery::mock(EmailPriceListIngestionDispatcher::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->once()->andReturn($client);
        $service->shouldReceive('resolveFolder')->once()->with($client, 'INBOX')->andReturn($folder);
        $this->app->instance(YandexMailboxService::class, $service);

        return $service;
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create(array_merge([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => 42,
            'is_seen' => false,
        ], $attributes));
    }

    private function user(bool $verified = true): User
    {
        return (new User)->forceFill([
            'id' => 1,
            'email' => 'operator@example.test',
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function url(MailMessage $message): string
    {
        return '/api/mail-messages/'.$message->id.'/mark-read';
    }
}
