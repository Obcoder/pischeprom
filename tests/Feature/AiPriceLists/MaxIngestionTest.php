<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\SafeRemoteFileDownloader;
use App\Jobs\AiPriceLists\IngestMaxPriceListAttachment;
use App\Models\MaxWebhookEvent;
use App\Models\PriceListImport;
use Illuminate\Support\Facades\Queue;
use Mockery;

class MaxIngestionTest extends AiPriceListTestCase
{
    public function test_webhook_checks_secret_deduplicates_and_only_dispatches_background_job(): void
    {
        Queue::fake();
        config()->set('services.max.webhook_secret', 'expected-secret');
        $payload = $this->payload();

        $this->postJson('/api/max/webhook', $payload)->assertForbidden();
        $this->withHeader('X-Max-Bot-Api-Secret', 'expected-secret')
            ->postJson('/api/max/webhook', $payload)
            ->assertOk()->assertJson(['ok' => true, 'processed' => 1]);
        $this->withHeader('X-Max-Bot-Api-Secret', 'expected-secret')
            ->postJson('/api/max/webhook', $payload)
            ->assertOk()->assertJson(['ok' => true, 'processed' => 0]);

        $this->assertDatabaseCount('max_webhook_events', 1);
        Queue::assertPushed(IngestMaxPriceListAttachment::class, 1);
        $this->assertDatabaseCount('price_list_imports', 0);
    }

    public function test_unknown_max_sender_is_processed_and_explicitly_marked_supplier_unresolved(): void
    {
        $contents = "Наименование;Цена;Валюта\nСухое молоко;310,50;RUB\n";
        $downloader = Mockery::mock(SafeRemoteFileDownloader::class);
        $downloader->shouldReceive('download')->once()->with('https://cdn.max.ru/files/price-1')->andReturn([
            'content' => $contents,
            'content_type' => 'text/plain',
            'size' => strlen($contents),
        ]);
        $this->app->instance(SafeRemoteFileDownloader::class, $downloader);
        $event = MaxWebhookEvent::query()->create([
            'update_id' => 'update-1',
            'deduplication_key' => hash('sha256', 'update-1'),
            'update_type' => 'message_created',
            'chat_id' => 'chat-unknown',
            'user_id' => 'user-unknown',
            'payload' => $this->payload(),
            'processed_at' => now(),
        ]);

        app()->call([new IngestMaxPriceListAttachment($event->id, 0), 'handle']);
        $import = PriceListImport::query()->sole();

        $this->assertSame(PriceListStatus::SupplierUnresolved, $import->status);
        $this->assertNull($import->entity_id);
        $this->assertSame('max', $import->source_channel->value);
        $this->assertSame(1, $import->items_total);
        $this->assertDatabaseCount('supplier_good_prices', 0);
    }

    private function payload(): array
    {
        return [
            'update_type' => 'message_created',
            'timestamp' => 1785834000000,
            'message' => [
                'sender' => ['user_id' => 'user-unknown', 'name' => 'Тестовый отправитель'],
                'recipient' => ['chat_id' => 'chat-unknown'],
                'body' => [
                    'mid' => 'message-1',
                    'text' => 'Прайс на август',
                    'attachments' => [[
                        'type' => 'file',
                        'payload' => [
                            'url' => 'https://cdn.max.ru/files/price-1',
                            'token' => 'opaque-token',
                            'filename' => 'Прайс MAX.csv',
                        ],
                    ]],
                ],
            ],
        ];
    }
}
