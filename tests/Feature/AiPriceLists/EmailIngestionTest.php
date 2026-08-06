<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\EmailPriceListAttachmentCollector;
use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Domain\AiPriceLists\Services\PriceListIngestionService;
use App\Jobs\AiPriceLists\IngestEmailPriceListAttachments;
use App\Jobs\AiPriceLists\ValidatePriceListFile;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\IncomingMailMaxNotificationDispatcher;
use App\Services\Mail\MailboxRegistry;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;

class EmailIngestionTest extends AiPriceListTestCase
{
    public function test_incoming_attachments_create_one_import_each_reuse_file_and_resolve_exact_email(): void
    {
        Queue::fake();
        $supplier = Entity::query()->create(['name' => 'Тестовый поставщик']);
        $email = Email::query()->create(['address' => 'supplier@example.test']);
        $email->entities()->attach($supplier);
        $message = $this->message(['from_address' => 'SUPPLIER@example.test']);
        Storage::disk('local')->put('mail/price.csv', "Наименование;Цена\nМука;100\n");
        Storage::disk('local')->put('mail/second.xlsx', 'queued-before-validation');

        $first = MailMessageAttachment::query()->create($this->attachment($message, 'mail/price.csv', 'Прайс август.csv'));
        MailMessageAttachment::query()->create($this->attachment($message, 'mail/second.xlsx', 'Цены.xlsx'));

        $this->assertDatabaseCount('price_list_imports', 2);
        $this->assertDatabaseHas('price_list_imports', [
            'mail_message_id' => $message->id,
            'entity_id' => $supplier->id,
            'path' => 'mail/price.csv',
            'status' => 'queued',
        ]);
        $this->assertSame(2, Queue::pushed(ValidatePriceListFile::class)->count());

        app(PriceListIngestionService::class)->ingestMailAttachment($first);
        $this->assertDatabaseCount('price_list_imports', 2);
        $this->assertSame(2, Queue::pushed(ValidatePriceListFile::class)->count());
    }

    public function test_inline_logo_and_unsupported_attachment_are_ignored(): void
    {
        Queue::fake();
        $message = $this->message();
        Storage::disk('local')->put('mail/logo.png', str_repeat('x', 500));
        Storage::disk('local')->put('mail/readme.txt', 'not a price list');

        MailMessageAttachment::query()->create($this->attachment($message, 'mail/logo.png', 'logo.png', [
            'mime_type' => 'image/png',
            'size' => 500,
            'disposition' => 'inline',
            'content_id' => '<logo>',
        ]));
        MailMessageAttachment::query()->create($this->attachment($message, 'mail/readme.txt', 'readme.txt'));

        $this->assertDatabaseCount('price_list_imports', 0);
        Queue::assertNothingPushed();
    }

    public function test_collector_saves_only_eligible_remote_attachments_and_is_idempotent(): void
    {
        Queue::fake();
        $message = $this->message();
        $mailbox = Mockery::mock(\App\Services\Mail\YandexMailboxService::class);
        $remoteAttachments = [
            [
                'index' => 0,
                'original_name' => 'Прайс август.xlsx',
                'file_name' => 'Прайс август.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size' => 20480,
                'content_id' => null,
                'disposition' => 'attachment',
            ],
            [
                'index' => 1,
                'original_name' => 'logo.png',
                'file_name' => 'logo.png',
                'mime_type' => 'image/png',
                'size' => 500,
                'content_id' => '<logo>',
                'disposition' => 'inline',
            ],
            [
                'index' => 2,
                'original_name' => 'readme.txt',
                'file_name' => 'readme.txt',
                'mime_type' => 'text/plain',
                'size' => 100,
                'content_id' => null,
                'disposition' => 'attachment',
            ],
        ];

        $mailbox
            ->shouldReceive('storeAttachmentsMatching')
            ->twice()
            ->with(
                Mockery::on(fn (MailMessage $candidate) => $candidate->is($message)),
                Mockery::type('callable'),
            )
            ->andReturnUsing(function (MailMessage $candidate, callable $rejectionReason) use ($remoteAttachments): array {
                $reasons = collect($remoteAttachments)
                    ->map(fn (array $metadata) => $rejectionReason($metadata))
                    ->all();
                $this->assertSame([null, 'inline_image', 'unsupported_extension'], $reasons);
                Storage::disk('local')->put('mail/custom/remote-price.xlsx', 'safe spreadsheet placeholder');
                $attachment = MailMessageAttachment::withoutEvents(fn () => MailMessageAttachment::query()->firstOrCreate(
                    [
                        'mail_message_id' => $candidate->id,
                        'original_name' => 'Прайс август.xlsx',
                        'size' => 20480,
                    ],
                    [
                        'disk' => 'local',
                        'path' => 'mail/custom/remote-price.xlsx',
                        'file_name' => 'remote-price.xlsx',
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'disposition' => 'attachment',
                        'saved_to_disk_at' => now(),
                    ],
                ));

                return [
                    'available' => 3,
                    'eligible' => 1,
                    'saved_attachment_ids' => [$attachment->id],
                    'failed' => 0,
                    'skipped' => [
                        'inline_image' => 1,
                        'unsupported_extension' => 1,
                    ],
                ];
            });

        $collector = new EmailPriceListAttachmentCollector(
            $mailbox,
            app(\App\Domain\AiPriceLists\Services\PriceListDocumentClassifier::class),
            app(PriceListIngestionService::class),
        );

        $first = $collector->collect($message);
        $second = $collector->collect($message);

        $this->assertSame([
            'available' => 3,
            'eligible' => 1,
            'saved' => 1,
            'ingested' => 1,
            'failed' => 0,
            'skipped' => [
                'inline_image' => 1,
                'unsupported_extension' => 1,
            ],
        ], $first);
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('mail_message_attachments', 1);
        $this->assertDatabaseHas('mail_message_attachments', ['path' => 'mail/custom/remote-price.xlsx']);
        $this->assertDatabaseCount('price_list_imports', 1);
        Queue::assertPushed(ValidatePriceListFile::class, 1);
    }

    public function test_real_mail_collector_reuses_custom_saved_file_and_persists_only_matching_remote_files(): void
    {
        Queue::fake();
        config()->set('services.yandex_mail.attachments_disk', 'local');
        $message = $this->message(['imap_uid' => 6101]);
        $existingContent = 'existing spreadsheet placeholder';
        Storage::disk('local')->put('mail/custom/existing-price.xlsx', $existingContent);
        $existing = MailMessageAttachment::withoutEvents(fn () => MailMessageAttachment::query()->create([
            'mail_message_id' => $message->id,
            'disk' => 'local',
            'path' => 'mail/custom/existing-price.xlsx',
            'original_name' => 'Прайс август.xlsx',
            'file_name' => 'existing-price.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => strlen($existingContent),
            'disposition' => 'attachment',
            'saved_to_disk_at' => now(),
        ]));
        $remoteMessage = new class($existingContent)
        {
            public function __construct(private readonly string $existingContent) {}

            public function getAttachments(): array
            {
                return [
                    [
                        'name' => 'Прайс август.xlsx',
                        'content' => $this->existingContent,
                        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'disposition' => 'attachment',
                    ],
                    [
                        'name' => 'logo.png',
                        'content' => str_repeat('x', 500),
                        'mime_type' => 'image/png',
                        'content_id' => '<logo>',
                        'disposition' => 'inline',
                    ],
                    [
                        'name' => 'цены.csv',
                        'content' => "Наименование;Цена\nМука;100\n",
                        'mime_type' => 'text/csv',
                        'disposition' => 'attachment',
                    ],
                ];
            }
        };
        $query = new class($remoteMessage)
        {
            public function __construct(private readonly object $message) {}

            public function leaveUnread(): self
            {
                return $this;
            }

            public function setFetchBody(bool $fetch): self
            {
                return $this;
            }

            public function setFetchAttachment(bool $fetch): self
            {
                return $this;
            }

            public function getMessageByUid(int $uid): object
            {
                return $this->message;
            }
        };
        $folder = new class($query)
        {
            public function __construct(private readonly object $query) {}

            public function query(): object
            {
                return $this->query;
            }
        };
        $client = new class
        {
            public bool $connected = false;

            public function connect(): void
            {
                $this->connected = true;
            }

            public function disconnect(): void
            {
                $this->connected = false;
            }
        };
        $registry = Mockery::mock(MailboxRegistry::class);
        $registry->shouldReceive('find')->once()->with('office@example.test')->andReturn([
            'address' => 'office@example.test',
        ]);
        $service = new class($registry, Mockery::mock(IncomingMailMaxNotificationDispatcher::class), Mockery::mock(EmailPriceListIngestionDispatcher::class), $client, $folder) extends YandexMailboxService
        {
            public function __construct(
                MailboxRegistry $mailboxes,
                IncomingMailMaxNotificationDispatcher $maxNotifications,
                EmailPriceListIngestionDispatcher $priceListIngestion,
                private readonly object $fakeClient,
                private readonly object $fakeFolder,
            ) {
                parent::__construct($mailboxes, $maxNotifications, $priceListIngestion);
            }

            protected function client(?array $mailbox = null): object
            {
                return $this->fakeClient;
            }

            protected function resolveFolder($client, string $folderName): object
            {
                return $this->fakeFolder;
            }
        };
        $collector = new EmailPriceListAttachmentCollector(
            $service,
            app(\App\Domain\AiPriceLists\Services\PriceListDocumentClassifier::class),
            app(PriceListIngestionService::class),
        );

        $report = $collector->collect($message);

        $this->assertSame(3, $report['available']);
        $this->assertSame(2, $report['eligible']);
        $this->assertSame(2, $report['saved']);
        $this->assertSame(2, $report['ingested']);
        $this->assertSame(['inline_image' => 1], $report['skipped']);
        $this->assertFalse($client->connected);
        $this->assertSame('mail/custom/existing-price.xlsx', $existing->fresh()->path);
        $this->assertDatabaseCount('mail_message_attachments', 2);
        $this->assertDatabaseCount('price_list_imports', 2);
        Queue::assertPushed(ValidatePriceListFile::class, 2);
    }

    public function test_mail_backfill_is_dry_by_default_then_dispatches_a_bounded_newest_batch(): void
    {
        Queue::fake();
        $oldest = $this->message(['imap_uid' => 4001]);
        $middle = $this->message(['imap_uid' => 4002]);
        $newest = $this->message(['imap_uid' => 4003]);
        $this->message(['imap_uid' => 4004, 'has_attachments' => false]);
        $this->message(['imap_uid' => 4005, 'direction' => 'outgoing']);

        $this->artisan('price-lists:mail-backfill', ['--limit' => 2])
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('Состояние не изменялось')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('price-lists:mail-backfill', [
            '--apply' => true,
            '--limit' => 2,
        ])->assertSuccessful();

        $jobs = Queue::pushed(IngestEmailPriceListAttachments::class);
        $this->assertCount(2, $jobs);
        $this->assertEqualsCanonicalizing(
            [$middle->id, $newest->id],
            $jobs->pluck('mailMessageId')->all(),
        );
        $this->assertNotContains($oldest->id, $jobs->pluck('mailMessageId')->all());
        $jobs->each(function (IngestEmailPriceListAttachments $job): void {
            $this->assertSame('sync', $job->connection);
            $this->assertSame('mail-sync', $job->queue);
        });
    }

    public function test_mail_backfill_apply_refuses_to_run_while_module_is_disabled(): void
    {
        Queue::fake();
        $this->message(['imap_uid' => 5001]);
        config()->set('ai-price-lists.enabled', false);

        $this->artisan('price-lists:mail-backfill', ['--apply' => true])
            ->expectsOutputToContain('AI-модуль прайс-листов выключен')
            ->assertFailed();
        Queue::assertNothingPushed();
    }

    public function test_deterministic_csv_pipeline_reaches_supplier_unresolved_without_external_ai(): void
    {
        Http::preventStrayRequests();
        $message = $this->message(['from_address' => 'unknown@example.test']);
        $contents = "Наименование;Артикул;Цена;Валюта;НДС\nМука пшеничная;00017;1 250,50;RUB;с НДС 20%\nСахар;00018;980.00;RUB;без НДС\n";
        Storage::disk('local')->put('mail/full-price.csv', $contents);
        $attachment = MailMessageAttachment::withoutEvents(fn () => MailMessageAttachment::query()->create(
            $this->attachment($message, 'mail/full-price.csv', 'Прайс поставщика.csv', ['size' => strlen($contents)])
        ));

        $import = app(PriceListIngestionService::class)->ingestMailAttachment($attachment)->refresh();

        $this->assertSame(PriceListStatus::SupplierUnresolved, $import->status);
        $this->assertSame(2, $import->items_total);
        $this->assertSame(2, $import->items_unmatched);
        $this->assertDatabaseHas('price_list_import_items', [
            'price_list_import_id' => $import->id,
            'supplier_sku' => '00017',
            'price' => 1250.5,
            'currency_code' => 'RUB',
        ]);
        $this->assertDatabaseCount('ai_usage_records', 0);
        $this->assertDatabaseHas('price_list_events', ['price_list_import_id' => $import->id, 'event_type' => 'review_ready']);
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create([
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => random_int(1000, 999999),
            'message_id' => '<'.uniqid().'@example.test>',
            'subject' => 'Прайс-лист на август',
            'message_date' => now(),
            'from_address' => 'supplier@example.test',
            'from_name' => 'Поставщик',
            'preview' => 'Актуальные цены',
            'has_attachments' => true,
            ...$attributes,
        ]);
    }

    private function attachment(MailMessage $message, string $path, string $name, array $attributes = []): array
    {
        return [
            'mail_message_id' => $message->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'file_name' => basename($path),
            'mime_type' => 'text/plain',
            'size' => Storage::disk('local')->size($path),
            'disposition' => 'attachment',
            'saved_to_disk_at' => now(),
            ...$attributes,
        ];
    }
}
