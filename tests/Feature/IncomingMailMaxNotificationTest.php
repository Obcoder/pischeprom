<?php

namespace Tests\Feature;

use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Jobs\SendIncomingMailMaxNotificationJob;
use App\Models\MailMessage;
use App\Models\MailMessageMaxDelivery;
use App\Services\Mail\IncomingMailMaxMessageFormatter;
use App\Services\Mail\IncomingMailMaxNotificationDispatcher;
use App\Services\Mail\IncomingMailMaxNotificationSender;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\YandexMailboxService;
use App\Services\MaxMessengerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IncomingMailMaxNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        config()->set('queue.default', 'sync');
        DB::purge();
        DB::setDefaultConnection('sqlite');

        $this->createSchema();

        config()->set([
            'app.url' => 'https://crm.test',
            'services.max.api_url' => 'https://platform-api2.max.ru',
            'services.max.access_token' => 'test-token',
            'services.max.ssl_verify' => false,
            'services.max.upload_timeout' => 30,
            'services.max.mail_notifications.enabled' => true,
            'services.max.mail_notifications.mailboxes' => ['com@food-server.ru'],
            'services.max.mail_notifications.folders' => ['INBOX'],
            'services.max.mail_notifications.chat_ids' => ['9001'],
            'services.max.mail_notifications.user_ids' => [],
            'services.max.mail_notifications.queue' => 'mail-sync',
            'services.max.mail_notifications.text_chunk_length' => 500,
            'services.max.mail_notifications.send_interval_ms' => 0,
            'services.max.mail_notifications.upload_processing_delay_ms' => 0,
            'services.max.mail_notifications.max_attachment_bytes' => 52428800,
            'services.max.mail_notifications.max_message_age_hours' => 72,
        ]);
        URL::forceRootUrl('https://crm.test');
    }

    public function test_dispatcher_queues_each_message_and_target_only_once(): void
    {
        Queue::fake();
        $message = $this->mailMessage([
            'message_id' => '<same-message@example.test>',
        ]);
        $dispatcher = app(IncomingMailMaxNotificationDispatcher::class);

        $dispatcher->register($message);
        $dispatcher->register($message->fresh());

        $duplicateImapRow = $this->mailMessage([
            'imap_uid' => 102,
            'message_id' => '<same-message@example.test>',
        ]);
        $dispatcher->register($duplicateImapRow);

        $this->assertDatabaseCount('mail_message_max_deliveries', 1);
        Queue::assertPushed(
            SendIncomingMailMaxNotificationJob::class,
            fn (SendIncomingMailMaxNotificationJob $job) => $job->deliveryId === 1
                && $job->queue === 'mail-sync',
        );
        Queue::assertPushed(SendIncomingMailMaxNotificationJob::class, 1);
    }

    public function test_dispatcher_ignores_other_mailboxes_outgoing_mail_and_non_inbox_folders(): void
    {
        Queue::fake();
        $dispatcher = app(IncomingMailMaxNotificationDispatcher::class);

        $dispatcher->register($this->mailMessage([
            'mailbox' => 'office@example.test',
            'imap_uid' => 201,
        ]));
        $dispatcher->register($this->mailMessage([
            'direction' => 'outgoing',
            'imap_uid' => 202,
        ]));
        $dispatcher->register($this->mailMessage([
            'folder' => 'Sent',
            'imap_uid' => 203,
        ]));

        $this->assertDatabaseCount('mail_message_max_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_mail_sync_registers_notification_only_when_the_imap_row_is_first_created(): void
    {
        $registry = Mockery::mock(MailboxRegistry::class);
        $registry
            ->shouldReceive('default')
            ->twice()
            ->andReturn(['address' => 'com@food-server.ru']);
        $dispatcher = Mockery::mock(IncomingMailMaxNotificationDispatcher::class);
        $dispatcher
            ->shouldReceive('safeRegister')
            ->once()
            ->with(Mockery::on(
                fn (MailMessage $message) => $message->mailbox === 'com@food-server.ru'
                    && $message->imap_uid === 301,
            ));
        $priceLists = Mockery::mock(EmailPriceListIngestionDispatcher::class);
        $priceLists
            ->shouldReceive('safeRegister')
            ->once()
            ->with(Mockery::on(
                fn (MailMessage $message) => $message->mailbox === 'com@food-server.ru'
                    && $message->imap_uid === 301
                    && $message->has_attachments,
            ));
        $service = new class($registry, $dispatcher, $priceLists) extends YandexMailboxService
        {
            public function storeForTest(object $message, array $mailbox): void
            {
                $this->storeMessage($message, 'INBOX', null, $mailbox);
            }
        };
        $imapMessage = new class
        {
            public int $uid = 301;

            public string $message_id = '<sync-message@example.test>';

            public string $subject = 'Новое письмо';

            public string $date = '2026-07-26 12:00:00';

            public object $header;

            public function __construct()
            {
                $this->header = (object) ['raw' => 'Content-Type: multipart/mixed; boundary="mail"'];
            }
        };
        $mailbox = ['address' => 'com@food-server.ru'];

        $service->storeForTest($imapMessage, $mailbox);
        $service->storeForTest($imapMessage, $mailbox);

        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseHas('mail_messages', [
            'subject' => 'Новое письмо',
            'has_attachments' => true,
        ]);
    }

    public function test_sender_delivers_the_complete_text_attachment_and_link_without_repeating_sent_delivery(): void
    {
        $body = implode(' ', array_map(
            fn (int $index) => "фрагмент-{$index}",
            range(1, 500),
        ));
        $message = $this->mailMessage([
            'text' => $body,
            'body_loaded_at' => now(),
            'has_attachments' => true,
        ]);
        $delivery = $this->delivery($message);
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox
            ->shouldReceive('loadBody')
            ->once()
            ->andReturnUsing(function (MailMessage $mailMessage): MailMessage {
                $mailMessage->setAttribute('available_attachments', [[
                    'index' => 0,
                    'original_name' => 'offer.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 12,
                ]]);

                return $mailMessage;
            });
        $mailbox
            ->shouldReceive('downloadAttachment')
            ->once()
            ->with(Mockery::type(MailMessage::class), 0)
            ->andReturn([
                'name' => 'offer.pdf',
                'mime_type' => 'application/pdf',
                'content' => 'pdf contents',
            ]);

        $providerMessage = 0;
        Http::fake(function (ClientRequest $request) use (&$providerMessage) {
            if (str_contains($request->url(), '/uploads')) {
                return Http::response([
                    'url' => 'https://fu.oneme.ru/upload.do?slot=mail-1',
                ]);
            }

            if (str_starts_with($request->url(), 'https://fu.oneme.ru/')) {
                return Http::response([
                    'token' => 'attachment-token-1',
                ]);
            }

            $providerMessage++;

            return Http::response([
                'message' => [
                    'body' => ['mid' => "max-message-{$providerMessage}"],
                ],
            ]);
        });

        $sender = new IncomingMailMaxNotificationSender(
            $mailbox,
            app(MaxMessengerService::class),
            app(IncomingMailMaxMessageFormatter::class),
        );
        $sender->send($delivery);

        $delivery->refresh();
        $this->assertSame(MailMessageMaxDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertGreaterThan(1, $delivery->text_parts_total);
        $this->assertSame($delivery->text_parts_total, $delivery->text_parts_sent);
        $this->assertSame(1, $delivery->attachments_total);
        $this->assertSame(1, $delivery->attachments_sent);
        $this->assertSame('attachment-token-1', $delivery->attachment_tokens['0']['token']);

        $messageRequests = $this->maxMessageRequests();
        $this->assertCount($delivery->text_parts_total + 1, $messageRequests);
        $sentText = $messageRequests
            ->map(fn (ClientRequest $request) => (string) ($request->data()['text'] ?? ''))
            ->implode("\n");
        preg_match_all('/фрагмент-(\d+)/u', $sentText, $matches);
        $this->assertSame(
            range(1, 500),
            array_map('intval', $matches[1]),
            'Полный текст должен быть доставлен без пропущенных или повторённых фрагментов.',
        );

        $firstPayload = $messageRequests->first()->data();
        $attachmentPayload = $messageRequests->last()->data();
        $this->assertSame(
            'https://crm.test/Ameise/Mail?mail_message_id='.$message->id,
            data_get($firstPayload, 'attachments.0.payload.buttons.0.0.url'),
        );
        $this->assertSame('file', data_get($attachmentPayload, 'attachments.0.type'));
        $this->assertSame(
            'attachment-token-1',
            data_get($attachmentPayload, 'attachments.0.payload.token'),
        );
        $this->assertSame(
            'https://crm.test/Ameise/Mail?mail_message_id='.$message->id,
            data_get($attachmentPayload, 'attachments.1.payload.buttons.0.0.url'),
        );

        $requestCount = count(Http::recorded());
        $sender->send($delivery->fresh());
        $this->assertCount($requestCount, Http::recorded());
    }

    public function test_attachment_retry_reuses_uploaded_token_and_resumes_after_the_last_sent_part(): void
    {
        $message = $this->mailMessage([
            'text' => 'Короткий полный текст письма.',
            'body_loaded_at' => now(),
            'has_attachments' => true,
        ]);
        $delivery = $this->delivery($message);
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox
            ->shouldReceive('loadBody')
            ->twice()
            ->andReturnUsing(function (MailMessage $mailMessage): MailMessage {
                $mailMessage->setAttribute('available_attachments', [[
                    'index' => 0,
                    'original_name' => 'invoice.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 7,
                ]]);

                return $mailMessage;
            });
        $mailbox
            ->shouldReceive('downloadAttachment')
            ->once()
            ->andReturn([
                'name' => 'invoice.pdf',
                'mime_type' => 'application/pdf',
                'content' => 'invoice',
            ]);

        $messageCall = 0;
        $failAttachment = true;
        Http::fake(function (ClientRequest $request) use (&$messageCall, &$failAttachment) {
            if (str_contains($request->url(), '/uploads')) {
                return Http::response([
                    'url' => 'https://fu.oneme.ru/upload.do?slot=retry',
                ]);
            }

            if (str_starts_with($request->url(), 'https://fu.oneme.ru/')) {
                return Http::response([
                    'token' => 'reusable-token',
                ]);
            }

            $messageCall++;

            return $messageCall === 2 && $failAttachment
                ? Http::response(['message' => 'attachment.not.ready'], 503)
                : Http::response([
                    'message' => [
                        'body' => [
                            'mid' => $failAttachment
                                ? "message-{$messageCall}"
                                : 'attachment-retry-success',
                        ],
                    ],
                ]);
        });

        $sender = new IncomingMailMaxNotificationSender(
            $mailbox,
            app(MaxMessengerService::class),
            app(IncomingMailMaxMessageFormatter::class),
        );

        try {
            $sender->send($delivery);
            $this->fail('The first attachment send must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('attachment.not.ready', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(MailMessageMaxDelivery::STATUS_RETRYING, $delivery->status);
        $this->assertSame(1, $delivery->text_parts_sent);
        $this->assertSame(0, $delivery->attachments_sent);
        $this->assertSame('reusable-token', $delivery->attachment_tokens['0']['token']);

        $recordedBeforeRetry = count(Http::recorded());
        $failAttachment = false;

        $sender->send($delivery->fresh());

        $delivery->refresh();
        $this->assertSame(MailMessageMaxDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(1, $delivery->text_parts_sent);
        $this->assertSame(1, $delivery->attachments_sent);
        $this->assertSame(
            'attachment-retry-success',
            $delivery->provider_message_ids['attachment:0'],
        );

        $retryRequests = collect(Http::recorded())->slice($recordedBeforeRetry)->values();
        $this->assertCount(1, $retryRequests);
        $this->assertStringContainsString('/messages', $retryRequests->first()[0]->url());
    }

    public function test_media_upload_prefers_the_slot_token_over_the_upload_retval(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/uploads')) {
                return Http::response([
                    'url' => 'https://vu.okcdn.ru/upload.do?slot=video',
                    'token' => 'video-slot-token',
                ]);
            }

            return Http::response([
                'retval' => 'upload-completed',
            ]);
        });

        $result = app(MaxMessengerService::class)->uploadAttachment(
            content: 'video contents',
            fileName: 'presentation.mp4',
            mimeType: 'video/mp4',
            type: 'video',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('video-slot-token', data_get($result, 'data.token'));
    }

    private function mailMessage(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'com@food-server.ru',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => 101,
            'message_id' => '<message-'.uniqid('', true).'@example.test>',
            'subject' => 'Запрос оборудования',
            'message_date' => now(),
            'from_address' => 'client@example.test',
            'from_name' => 'Клиент',
            'text' => 'Текст письма.',
            'body_loaded_at' => now(),
            'has_attachments' => false,
            ...$attributes,
        ]);
    }

    private function delivery(MailMessage $message): MailMessageMaxDelivery
    {
        return MailMessageMaxDelivery::query()->create([
            'mail_message_id' => $message->id,
            'target_type' => MailMessageMaxDelivery::TARGET_CHAT,
            'target_id' => '9001',
            'status' => MailMessageMaxDelivery::STATUS_PENDING,
        ]);
    }

    private function maxMessageRequests()
    {
        return collect(Http::recorded())
            ->map(fn (array $record) => $record[0])
            ->filter(fn (ClientRequest $request) => str_contains($request->url(), '/messages'))
            ->values();
    }

    private function createSchema(): void
    {
        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('mailbox')->index();
            $table->string('folder')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->unsignedBigInteger('reply_to_mail_message_id')->nullable();
            $table->text('in_reply_to')->nullable();
            $table->longText('references')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('message_date')->nullable()->index();
            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->text('preview')->nullable();
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('body_loaded_at')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->longText('raw_headers')->nullable();
            $table->unique(['mailbox', 'folder', 'imap_uid']);
        });

        Schema::create('mail_message_max_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mail_message_id');
            $table->string('target_type');
            $table->string('target_id');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('text_parts_total')->default(0);
            $table->unsignedSmallInteger('text_parts_sent')->default(0);
            $table->unsignedSmallInteger('attachments_total')->default(0);
            $table->unsignedSmallInteger('attachments_sent')->default(0);
            $table->json('attachment_tokens')->nullable();
            $table->json('skipped_attachments')->nullable();
            $table->json('provider_message_ids')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['mail_message_id', 'target_type', 'target_id']);
        });
    }
}
