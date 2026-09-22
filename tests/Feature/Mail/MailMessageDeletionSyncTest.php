<?php

namespace Tests\Feature\Mail;

use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Models\MailMessage;
use App\Services\Mail\IncomingMailMaxNotificationDispatcher;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\MailDeletionException;
use App\Services\Mail\MailRemoteDeletion;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MailMessageDeletionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_confirmation_precedes_local_deletion_and_connection_is_closed(): void
    {
        $message = $this->message();
        [$service, $client, $folder] = $this->service();
        $remote = Mockery::mock(MailRemoteDeletion::class);
        $remote->shouldReceive('delete')->once()->with($client, $folder, Mockery::on(fn ($value) => $value->id === $message->id))
            ->andReturnUsing(function () use ($message): void {
                $this->assertNotNull($message->fresh());
                $this->assertNull($message->fresh()->deleted_at);
            });
        $this->app->instance(MailRemoteDeletion::class, $remote);

        $service->deleteMessage($message);

        $this->assertSoftDeleted($message);
        $this->assertNull(MailMessage::find($message->id));
        $this->assertSame('<delete@example.test>', MailMessage::withTrashed()->findOrFail($message->id)->message_id);
    }

    public function test_remote_failure_preserves_message_and_releases_connection_and_lock(): void
    {
        $message = $this->message();
        [$service] = $this->service();
        $remote = Mockery::mock(MailRemoteDeletion::class);
        $remote->shouldReceive('delete')->once()->andThrow(new RuntimeException('Synthetic remote failure'));
        $this->app->instance(MailRemoteDeletion::class, $remote);

        try {
            $service->deleteMessage($message);
            $this->fail('Remote failure must abort local deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic remote failure', $exception->getMessage());
        }
        $this->assertNull($message->fresh()->deleted_at);
        $lock = Cache::lock('mail-message-mutation:'.hash('sha256', json_encode(['office@example.test', 'INBOX', '42'])), 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_local_only_mail_is_not_deleted_as_if_server_deletion_succeeded(): void
    {
        $message = $this->message(['imap_uid' => null]);
        $service = Mockery::mock(YandexMailboxService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('client');

        try {
            $service->deleteMessage($message);
            $this->fail('Missing server identity must be reported.');
        } catch (MailDeletionException $exception) {
            $this->assertSame(422, $exception->status);
        }
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_a_concurrent_completed_delete_does_not_repeat_remote_mutation(): void
    {
        $message = $this->message();
        $message->delete();
        $service = Mockery::mock(YandexMailboxService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldNotReceive('client');

        $service->deleteMessage($message);

        $this->assertSoftDeleted($message);
    }

    public function test_sync_payload_fetched_before_deletion_never_restores_mail_or_dispatches_side_effects(): void
    {
        $message = $this->message();
        $message->delete();
        $registry = Mockery::mock(MailboxRegistry::class);
        $notifications = Mockery::mock(IncomingMailMaxNotificationDispatcher::class);
        $notifications->shouldNotReceive('safeRegister');
        $priceLists = Mockery::mock(EmailPriceListIngestionDispatcher::class);
        $priceLists->shouldNotReceive('safeRegister');
        $service = new class($registry, $notifications, $priceLists) extends YandexMailboxService
        {
            public function storeStale(object $remote): void
            {
                $this->storeMessage($remote, 'INBOX', 'incoming', ['address' => 'office@example.test']);
            }
        };
        $stale = (object) [
            'uid' => 42, 'message_id' => '<delete@example.test>',
            'subject' => 'Stale sync result', 'date' => '2026-09-22 12:00:00',
            'header' => (object) ['raw' => 'Content-Type: text/plain'],
        ];

        $service->storeStale($stale);
        $service->storeStale($stale);

        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertSame(0, MailMessage::count());
        $this->assertSame('Original subject', MailMessage::withTrashed()->findOrFail($message->id)->subject);
    }

    public function test_reused_uid_creates_a_new_message_without_reviving_or_overwriting_deleted_mail(): void
    {
        $deleted = $this->message();
        $deleted->delete();
        $notifications = Mockery::mock(IncomingMailMaxNotificationDispatcher::class);
        $notifications->shouldReceive('safeRegister')->once();
        $priceLists = Mockery::mock(EmailPriceListIngestionDispatcher::class);
        $priceLists->shouldReceive('safeRegister')->once();
        $service = new class(Mockery::mock(MailboxRegistry::class), $notifications, $priceLists) extends YandexMailboxService
        {
            public function storeForTest(int $uid, string $messageId): void
            {
                $this->storeMessage((object) [
                    'uid' => $uid, 'message_id' => $messageId, 'subject' => 'New server payload',
                    'date' => '2026-09-22 12:00:00', 'header' => (object) ['raw' => 'Content-Type: text/plain'],
                ], 'INBOX', 'outgoing', ['address' => 'office@example.test']);
            }
        };

        // Resync with a changed UID cannot bring back the deleted Message-ID.
        $service->storeForTest(99, '<delete@example.test>');
        $this->assertSame(0, MailMessage::count());
        // A different message after a folder reset must not disappear behind the old UID.
        $service->storeForTest(42, '<new-message@example.test>');
        $new = MailMessage::sole();
        $this->assertNotSame($deleted->id, $new->id);
        $this->assertSame('<new-message@example.test>', $new->message_id);
        $service->storeForTest(42, '<delete@example.test>');
        $this->assertSame('<new-message@example.test>', $new->fresh()->message_id);
        $this->assertSame(1, MailMessage::count());
        $this->assertSame(2, MailMessage::withTrashed()->count());
        $archived = MailMessage::withTrashed()->findOrFail($deleted->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame('Original subject', $archived->subject);
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::create([
            'mailbox' => 'office@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'imap_uid' => 42, 'message_id' => '<delete@example.test>', 'subject' => 'Original subject', ...$attributes,
        ]);
    }

    private function service(): array
    {
        $registry = Mockery::mock(MailboxRegistry::class);
        $registry->shouldReceive('find')->once()->with('office@example.test')->andReturn(['address' => 'office@example.test']);
        $client = Mockery::mock();
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('disconnect')->once();
        $folder = (object) ['path' => 'INBOX'];
        $service = Mockery::mock(YandexMailboxService::class, [
            $registry, Mockery::mock(IncomingMailMaxNotificationDispatcher::class), Mockery::mock(EmailPriceListIngestionDispatcher::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('client')->once()->andReturn($client);
        $service->shouldReceive('resolveFolder')->once()->with($client, 'INBOX')->andReturn($folder);

        return [$service, $client, $folder];
    }
}
