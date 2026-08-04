<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\PriceListIngestionService;
use App\Jobs\AiPriceLists\ValidatePriceListFile;
use App\Models\Email;
use App\Models\Entity;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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
