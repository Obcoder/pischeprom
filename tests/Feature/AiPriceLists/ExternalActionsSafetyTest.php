<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\MaxPriceListNotifier;
use App\Domain\AiPriceLists\Services\PriceListAiClassifier;
use App\Domain\AiPriceLists\Services\PriceListParserManager;
use App\Domain\AiPriceLists\Services\PriceListStatusNotificationService;
use App\Domain\AiPriceLists\Services\StructuredPriceListExtractor;
use App\Jobs\AiPriceLists\ExtractPriceListContent;
use App\Jobs\AiPriceLists\FinalizePriceListForReview;
use App\Jobs\AiPriceLists\MatchPriceListItems;
use App\Jobs\AiPriceLists\NormalizePriceListRows;
use App\Jobs\AiPriceLists\RecognizePriceListWithOcr;
use App\Models\PriceListImportItem;
use App\Notifications\PriceListAlertNotification;
use App\Services\MaxMessengerService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ExternalActionsSafetyTest extends AiPriceListTestCase
{
    public function test_async_stages_share_one_lock_and_wait_without_running_concurrently(): void
    {
        config()->set('ai-price-lists.queue_connection', 'database');
        $extract = new ExtractPriceListContent(42);
        $normalize = new NormalizePriceListRows(42);
        $first = collect($extract->middleware())->first(fn ($middleware) => $middleware instanceof WithoutOverlapping);
        $second = collect($normalize->middleware())->first(fn ($middleware) => $middleware instanceof WithoutOverlapping);
        $this->assertSame($first->getLockKey($extract), $second->getLockKey($normalize));
        $lock = Cache::lock($first->getLockKey($extract), 60);
        $this->assertTrue($lock->get());
        $normalize->withFakeQueueInteractions();

        try {
            $second->handle($normalize, fn () => $this->fail('A concurrent stage must wait for the import lock.'));
            $normalize->assertReleased(30);
        } finally {
            $lock->release();
        }
    }

    public function test_disabled_notifications_do_not_enqueue_email_or_send_any_max_message(): void
    {
        config()->set(['ai-price-lists.notifications_enabled' => false, 'ai-price-lists.max.send_acknowledgement' => true]);
        $this->userWith(['ai_price_lists.review']);
        $import = $this->import(['source_channel' => 'max', 'source_chat_id' => 'test-chat']);
        $max = Mockery::mock(MaxMessengerService::class);
        $max->shouldNotReceive('configured', 'sendToChat', 'sendToUser');
        $notifier = new MaxPriceListNotifier($max);

        app(PriceListStatusNotificationService::class)->statusChanged($import, PriceListStatus::ReviewRequired);
        $notifier->acknowledged($import);
        $notifier->ready($import);
        $notifier->failed($import, 'test');

        Notification::assertNothingSent();
        $this->assertEmpty($import->fresh()->document_metadata);
    }

    public function test_recipient_scope_includes_only_current_authorized_users(): void
    {
        config()->set('ai-price-lists.notifications_enabled', true);
        $allowed = $this->userWith(['ai_price_lists.review']);
        $customer = $this->userWith([]);
        $customer->update(['type' => 'customer']);
        $formerReviewer = $this->userWith([]);
        $blocked = $this->userWith(['ai_price_lists.review']);
        $blocked->update(['status' => 'blocked']);
        $import = $this->import(['reviewed_by' => $formerReviewer->id]);

        app(PriceListStatusNotificationService::class)->statusChanged($import, PriceListStatus::ReviewRequired);

        Notification::assertSentTo($allowed, PriceListAlertNotification::class);
        Notification::assertNotSentTo([$customer, $formerReviewer, $blocked], PriceListAlertNotification::class);
        Notification::assertCount(1);
    }

    public function test_delivery_rechecks_kill_switch_and_rejects_legacy_queued_notifications(): void
    {
        $user = $this->userWith(['ai_price_lists.review']);
        $manager = new ChannelManager($this->app);
        $mail = Mockery::mock(MailChannel::class);
        $mail->shouldNotReceive('send');
        $manager->extend('mail', fn () => $mail);
        $notification = new PriceListAlertNotification('test', 'test', '/test', 'ai_price_lists.review');
        config()->set('ai-price-lists.notifications_enabled', true);
        $queued = serialize(new SendQueuedNotifications([$user], $notification, ['mail']));

        config()->set('ai-price-lists.notifications_enabled', false);
        unserialize($queued)->handle($manager);

        config()->set('ai-price-lists.notifications_enabled', true);
        $legacy = new PriceListAlertNotification('old', 'old', '/test');
        unserialize(serialize(new SendQueuedNotifications([$user], $legacy, ['mail'])))->handle($manager);

        $user->revokePermissionTo('ai_price_lists.review');
        unserialize($queued)->handle($manager);
        $this->assertFalse($notification->shouldSend($user->fresh(), 'mail'));
    }

    public function test_local_csv_pipeline_still_works_without_ai_or_notifications(): void
    {
        config()->set(['ai-price-lists.ai.enabled' => false, 'ai-price-lists.notifications_enabled' => false, 'ai-price-lists.matching.ai_reranking_enabled' => true]);
        Queue::fake();
        Http::fake();
        $provider = Mockery::mock(StructuredTextModelProviderInterface::class);
        $provider->shouldNotReceive('generate', 'configured');
        $this->app->instance(StructuredTextModelProviderInterface::class, $provider);
        $import = $this->import(['status' => PriceListStatus::Extracting]);
        Storage::disk('local')->put($import->path, "Наименование;Цена;Валюта\nСухое молоко;310,50;RUB\n");

        foreach ([ExtractPriceListContent::class, NormalizePriceListRows::class, MatchPriceListItems::class, FinalizePriceListForReview::class] as $job) {
            app()->call([new $job($import->id), 'handle']);
        }

        $this->assertSame(PriceListStatus::ReviewRequired, $import->fresh()->status);
        $this->assertSame(1, $import->fresh()->items_total);
        $this->assertDatabaseCount('ai_usage_records', 0);
        Queue::assertNotPushed(RecognizePriceListWithOcr::class);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_old_ocr_job_and_direct_classifier_do_not_call_providers_when_disabled(): void
    {
        config()->set('ai-price-lists.ai.enabled', false);
        Queue::fake();
        $ocr = Mockery::mock(OcrProviderInterface::class);
        $ocr->shouldNotReceive('recognize', 'configured');
        $this->app->instance(OcrProviderInterface::class, $ocr);
        $provider = Mockery::mock(StructuredTextModelProviderInterface::class);
        $provider->shouldNotReceive('generate', 'configured');
        $this->app->instance(StructuredTextModelProviderInterface::class, $provider);
        $import = $this->import(['status' => PriceListStatus::Ocr]);

        app()->call([unserialize(serialize(new RecognizePriceListWithOcr($import->id))), 'handle']);
        $this->assertSame('ai_disabled', $import->fresh()->error_code);
        $this->assertFalse($import->fresh()->error_retryable);
        $this->assertSame(DocumentClass::Uncertain, app(PriceListAiClassifier::class)->classify($import, new ExtractionResult([], 'csv')));
        $this->assertFalse(app(StructuredPriceListExtractor::class)->configured());
        $this->assertDatabaseCount('ai_usage_records', 0);
    }

    public function test_mixed_pdf_keeps_local_text_without_enqueuing_ocr(): void
    {
        config()->set('ai-price-lists.ai.enabled', false);
        Queue::fake();
        $import = $this->import(['status' => PriceListStatus::Extracting, 'extension' => 'pdf']);
        Storage::disk('local')->put($import->path, 'fixture');
        $row = new ExtractedRow(1, ['Сахар', '100'], 'Сахар 100');
        $parser = Mockery::mock(PriceListParserManager::class);
        $parser->shouldReceive('parse')->once()->andReturn(new ExtractionResult([$row], 'pdf', true));
        $this->app->instance(PriceListParserManager::class, $parser);

        app()->call([new ExtractPriceListContent($import->id), 'handle']);

        $this->assertSame(PriceListStatus::Normalizing, $import->fresh()->status);
        $this->assertNotEmpty($import->fresh()->document_metadata['parser_warnings']);
        Queue::assertNotPushed(RecognizePriceListWithOcr::class);
        Queue::assertPushed(NormalizePriceListRows::class);
    }

    public function test_replayed_extraction_and_normalization_preserve_reviewed_rows(): void
    {
        config()->set('ai-price-lists.ai.enabled', false);
        Queue::fake();
        $import = $this->import(['status' => PriceListStatus::Extracting]);
        Storage::disk('local')->put($import->path, 'fixture');
        $row = new ExtractedRow(1, ['Сахар', '100'], 'Сахар 100');
        $item = PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id, 'position' => 1, 'row_fingerprint' => $row->fingerprint(),
            'raw_cells' => $row->cells, 'raw_text' => $row->text, 'raw_name' => 'Ручная правка',
            'price' => '123.000000', 'decision_status' => ItemDecisionStatus::Matched, 'reviewed_at' => now(),
        ]);
        $parser = Mockery::mock(PriceListParserManager::class);
        $parser->shouldReceive('parse')->once()->andReturn(new ExtractionResult([$row], 'csv'));
        $this->app->instance(PriceListParserManager::class, $parser);

        app()->call([new ExtractPriceListContent($import->id), 'handle']);
        app()->call([new NormalizePriceListRows($import->id), 'handle']);

        $this->assertSame('Ручная правка', $item->fresh()->raw_name);
        $this->assertSame('123.000000', $item->fresh()->price);
        $this->assertSame(ItemDecisionStatus::Matched, $item->fresh()->decision_status);
        $this->assertSame(1, $import->items()->count());
    }
}
