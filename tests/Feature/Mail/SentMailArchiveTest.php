<?php

namespace Tests\Feature\Mail;

use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\IncomingMailMaxNotificationDispatcher;
use App\Services\Mail\LegacySentReconstruction;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\SentCopyPendingException;
use App\Services\Mail\SentMailArchive;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\IMAP;

class SentMailArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.yandex_mail.mailboxes' => [[
            'address' => 'office@example.test', 'imap' => ['host' => 'imap.example.test', 'sent' => 'Sent'],
        ]], 'services.yandex_mail.sent_copy_append_mailboxes' => ['office@example.test']]);
    }

    public function test_missing_sent_copy_is_appended_once_and_retries_only_find_the_existing_copy(): void
    {
        $message = $this->message();
        [$archive, $folder] = $this->archive([null, 71]);
        $folder->shouldReceive('appendMessage')->once()->with(Mockery::on(fn ($raw) => str_contains($raw, 'Message-ID: <stable@example.test>')), ['\\Seen'], Mockery::any())->andReturn([]);
        $archive->archive($message);
        $this->assertSame(71, (int) $message->fresh()->imap_uid);
        $this->assertSame('linked', $message->fresh()->sent_copy_status);
        // A completed retry exits before connecting or appending again.
        $archive->archive($message);
        $this->assertDatabaseCount('mail_messages', 1);
    }

    public function test_provider_copy_is_linked_without_append_and_without_guessing_its_seen_flag(): void
    {
        $message = $this->message();
        [$archive, $folder] = $this->archive([71]);
        $folder->shouldNotReceive('appendMessage');
        $archive->archive($message);
        $this->assertNull($message->fresh()->is_seen);
        $this->assertSame(71, (int) $message->fresh()->imap_uid);
    }

    public function test_lost_append_response_is_recovered_by_search_without_a_second_append(): void
    {
        $message = $this->message();
        [$first, $folder] = $this->archive([null]);
        $folder->shouldReceive('appendMessage')->once()->andThrow(new RuntimeException('lost reply after server stored copy'));
        try {
            $first->archive($message);
            $this->fail('Unconfirmed APPEND must remain pending.');
        } catch (RuntimeException) {
            $this->assertNull($message->fresh()->imap_uid);
        }
        [$retry, $retryFolder] = $this->archive([71]);
        $retryFolder->shouldNotReceive('appendMessage');
        $retry->archive($message);
        $this->assertSame(71, (int) $message->fresh()->imap_uid);
    }

    public function test_fresh_smtp_receipt_waits_for_provider_copy_before_append(): void
    {
        $message = $this->message(['smtp_accepted_at' => now()]);
        [$archive, $folder] = $this->archive([null]);
        $folder->shouldNotReceive('appendMessage');
        $this->expectException(SentCopyPendingException::class);
        $archive->archive($message);
    }

    public function test_prepared_failed_and_unknown_deliveries_never_create_a_server_sent_copy(): void
    {
        $archive = Mockery::mock(SentMailArchive::class, [app(MailboxRegistry::class)])->makePartial()->shouldAllowMockingProtectedMethods();
        $archive->shouldNotReceive('client');
        foreach (['prepared', 'sending', 'unknown', 'failed'] as $status) {
            $message = $this->message(['delivery_status' => $status]);
            $archive->archive($message);
            $this->assertNull($message->fresh()->imap_uid);
            $this->assertSame($status, $message->fresh()->delivery_status);
        }
    }

    public function test_legacy_reconstruction_refuses_nonempty_sent_with_unaccounted_originals(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('examineFolder')->once()->with('Sent')->andReturn($this->response(['exists' => 1]));
        $connection->shouldReceive('search')->once()->with(['ALL'], IMAP::ST_UID)->andReturn($this->response([71]));
        $client = Mockery::mock();
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('disconnect')->once();
        $client->shouldReceive('getFolder')->once()->with('Sent')->andReturn((object) ['path' => 'Sent']);
        $client->shouldReceive('getConnection')->andReturn($connection);
        $archive = Mockery::mock(SentMailArchive::class, [app(MailboxRegistry::class)])->makePartial()->shouldAllowMockingProtectedMethods();
        $archive->shouldReceive('client')->once()->andReturn($client);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sent contains original or unrecognized messages');
        $archive->assertReconstructionDestination(app(MailboxRegistry::class)->find('office@example.test'));
    }

    public function test_search_verifies_exact_message_id_in_peek_headers_and_rejects_ambiguous_matches(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('escapeString')->with('stable@example.test')->andReturn('"stable@example.test"');
        $connection->shouldReceive('search')->with(['HEADER Message-ID "stable@example.test"'], IMAP::ST_UID)->andReturn($this->response([5, 6]));
        foreach ([5 => 'stable@example.test.extra', 6 => 'stable@example.test'] as $uid => $id) {
            $connection->shouldReceive('fetch')->with(['UID', 'BODY.PEEK[HEADER]'], [$uid], null, IMAP::ST_UID)
                ->andReturn($this->response([$uid => ['BODY[HEADER]' => "Message-ID: <{$id}>\r\n"]]));
        }
        $client = Mockery::mock();
        $client->shouldReceive('getConnection')->andReturn($connection);
        $this->assertSame(6, app(SentMailArchive::class)->findExactUid($client, '<stable@example.test>'));
    }

    public function test_multiple_exact_remote_copies_are_not_arbitrarily_linked_or_appended_again(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('escapeString')->andReturn('"stable@example.test"');
        $connection->shouldReceive('search')->andReturn($this->response([5, 6]));
        foreach ([5, 6] as $uid) {
            $connection->shouldReceive('fetch')->with(['UID', 'BODY.PEEK[HEADER]'], [$uid], null, IMAP::ST_UID)
                ->andReturn($this->response([$uid => ['BODY[HEADER]' => "Message-ID: <stable@example.test>\r\n"]]));
        }
        $client = Mockery::mock();
        $client->shouldReceive('getConnection')->andReturn($connection);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('More than one exact Sent copy');
        app(SentMailArchive::class)->findExactUid($client, '<stable@example.test>');
    }

    public function test_sync_links_exact_local_identity_preserving_the_row_and_attachment_metadata(): void
    {
        $local = $this->message();
        $attachment = MailMessageAttachment::create([
            'mail_message_id' => $local->id, 'disk' => 'local', 'path' => 'existing.pdf',
            'original_name' => 'existing.pdf', 'file_name' => 'existing.pdf', 'size' => 3,
        ]);
        $this->syncService()->storeForTest(71, '<stable@example.test>');
        $this->syncService()->storeForTest(71, 'stable@example.test');
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('mail_message_attachments', 1);
        $this->assertSame($local->id, $attachment->fresh()->mail_message_id);
        $this->assertSame('Original body', $local->fresh()->text);
        $this->assertSame('linked', $local->fresh()->sent_copy_status);
        $this->assertSame(71, (int) $local->fresh()->imap_uid);
    }

    public function test_sync_never_overwrites_an_active_mail_body_when_server_reuses_a_uid(): void
    {
        $old = $this->message(['imap_uid' => 71, 'sent_mime_path' => null, 'message_id' => '<old@example.test>']);
        $this->syncService()->storeForTest(71, '<different@example.test>');
        $this->assertSame('<old@example.test>', $old->fresh()->message_id);
        $this->assertSame('Original body', $old->fresh()->text);
        $this->assertNull($old->fresh()->imap_uid);
        $this->assertDatabaseCount('mail_messages', 2);
    }

    public function test_legacy_dry_run_has_no_mutations_and_refuses_unproven_attachments(): void
    {
        $eligible = $this->message(['message_id' => '<legacy@local.pischeprom>', 'sent_mime_path' => null, 'sent_copy_status' => 'legacy']);
        $skipped = $this->message(['message_id' => '<attachments@local.pischeprom>', 'sent_mime_path' => null, 'has_attachments' => true]);
        $archive = Mockery::mock(SentMailArchive::class);
        $archive->shouldReceive('assertReconstructionDestination')->once();
        $archive->shouldNotReceive('prepare');
        $archive->shouldNotReceive('archive');
        $service = new LegacySentReconstruction(app(MailboxRegistry::class), $archive);
        $rows = $service->run('office@example.test');
        $this->assertSame('eligible', $rows[0]['status']);
        $this->assertSame('attachment_completeness_unproven', $rows[1]['reason']);
        $this->assertSame('<legacy@local.pischeprom>', $eligible->fresh()->message_id);
        $this->assertFalse($eligible->fresh()->is_reconstructed);
        $this->assertNull($skipped->fresh()->imap_uid);
    }

    public function test_legacy_apply_marks_reconstruction_and_keeps_local_row_without_smtp(): void
    {
        $legacy = $this->message(['message_id' => '<legacy@local.pischeprom>', 'sent_mime_path' => null, 'sent_copy_status' => 'legacy']);
        $archive = Mockery::mock(SentMailArchive::class)->makePartial();
        $archive->shouldReceive('assertReconstructionDestination')->twice();
        $archive->shouldReceive('archive')->once()->with(Mockery::on(fn ($m) => $m->id === $legacy->id && $m->is_reconstructed));
        $rows = (new LegacySentReconstruction(app(MailboxRegistry::class), $archive))->run('office@example.test', true);
        $this->assertSame('reconstructed', $rows[0]['status']);
        $fresh = $legacy->fresh();
        $this->assertTrue($fresh->is_reconstructed);
        $this->assertStringContainsString('X-Pischeprom-Archive-Type: reconstructed', Storage::disk('local')->get($fresh->sent_mime_path));
        $this->assertDatabaseCount('mail_messages', 1);
    }

    private function message(array $attributes = []): MailMessage
    {
        $message = MailMessage::create([
            'mailbox' => 'office@example.test', 'folder' => 'Sent', 'direction' => 'outgoing',
            'message_id' => '<stable@example.test>', 'from_address' => 'office@example.test',
            'to' => [['address' => 'buyer@example.test', 'name' => null]],
            'subject' => 'Original', 'text' => 'Original body', 'body_loaded_at' => now(), 'message_date' => now(),
            'delivery_status' => 'sent', 'sent_copy_status' => 'pending', 'smtp_accepted_at' => now()->subMinute(), ...$attributes,
        ]);
        if (! array_key_exists('sent_mime_path', $attributes)) {
            $mime = (new Email)->from('office@example.test')->to('buyer@example.test')->subject('Original')->text('Original body');
            $mime->getHeaders()->addIdHeader('Message-ID', SentMailArchive::identity($message->message_id));
            app(SentMailArchive::class)->prepare($message, $mime);
        }

        return $message;
    }

    private function archive(array $searches): array
    {
        $client = Mockery::mock();
        $client->shouldReceive('connect')->once();
        $client->shouldReceive('disconnect')->once();
        $client->shouldReceive('openFolder')->once()->with('INBOX.Sent');
        $folder = Mockery::mock();
        $folder->path = 'INBOX.Sent';
        $client->shouldReceive('getFolder')->once()->with('Sent')->andReturn($folder);
        $archive = Mockery::mock(SentMailArchive::class, [app(MailboxRegistry::class)])->makePartial()->shouldAllowMockingProtectedMethods();
        $archive->shouldReceive('client')->once()->andReturn($client);
        foreach ($searches as $uid) {
            $archive->shouldReceive('findExactUid')->once()->ordered()->with($client, '<stable@example.test>')->andReturn($uid);
        }

        return [$archive, $folder];
    }

    private function syncService(): YandexMailboxService
    {
        $notifications = Mockery::mock(IncomingMailMaxNotificationDispatcher::class);
        $notifications->shouldReceive('safeRegister')->andReturnNull();
        $ingestion = Mockery::mock(EmailPriceListIngestionDispatcher::class);
        $ingestion->shouldReceive('safeRegister')->andReturn(false);

        return new class(app(MailboxRegistry::class), $notifications, $ingestion) extends YandexMailboxService
        {
            public function storeForTest(int $uid, string $messageId): void
            {
                $this->storeMessage((object) [
                    'uid' => $uid, 'message_id' => $messageId, 'subject' => 'Remote subject',
                    'header' => (object) ['raw' => 'Content-Type: text/plain'],
                ], 'Sent', 'outgoing', ['address' => 'office@example.test']);
            }
        };
    }

    private function response(array $data): Response
    {
        return Response::empty()->setResult($data)->setCanBeEmpty($data === []);
    }
}
