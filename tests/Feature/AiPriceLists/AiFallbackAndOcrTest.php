<?php

namespace Tests\Feature\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\DTO\OcrResponse;
use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Providers\FakeOcrProvider;
use App\Domain\AiPriceLists\Providers\FakeStructuredTextModelProvider;
use App\Jobs\AiPriceLists\ClassifyPriceListDocument;
use App\Jobs\AiPriceLists\ExtractPriceListContent;
use App\Jobs\AiPriceLists\NormalizePriceListRows;
use App\Jobs\AiPriceLists\RecognizePriceListWithOcr;
use App\Models\PriceListImportItem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class AiFallbackAndOcrTest extends AiPriceListTestCase
{
    public function test_ambiguous_text_document_uses_lightweight_ai_classification(): void
    {
        Bus::fake();
        $import = $this->import([
            'status' => PriceListStatus::Validating,
            'current_stage' => 'classify',
            'document_class' => 'uncertain',
            'path' => 'imports/ambiguous.csv',
            'extension' => 'csv',
        ]);
        Storage::disk('local')->put($import->path, "Код;Значение\nA1;12\nA2;14\n");
        $this->app->instance(StructuredTextModelProviderInterface::class, new FakeStructuredTextModelProvider([
            'class' => 'price_list',
            'confidence' => 0.97,
            'reason' => 'Строки похожи на товарные позиции.',
        ]));

        app()->call([new ClassifyPriceListDocument($import->id), 'handle']);

        $this->assertSame(PriceListStatus::Extracting, $import->fresh()->status);
        $this->assertDatabaseHas('ai_usage_records', [
            'price_list_import_id' => $import->id,
            'operation' => 'document_classification',
            'status' => 'success',
        ]);
        Bus::assertDispatched(ExtractPriceListContent::class);
    }

    public function test_ambiguous_local_extraction_uses_fake_strict_ai_and_keeps_locator_and_usage(): void
    {
        $import = $this->import(['status' => PriceListStatus::Normalizing, 'current_stage' => 'normalize', 'progress' => 45]);
        PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id,
            'position' => 1,
            'source_page' => 3,
            'source_row' => 8,
            'raw_cells' => ['text' => 'нестандартный макет'],
            'raw_text' => 'нестандартный макет',
            'row_fingerprint' => hash('sha256', 'ambiguous-row'),
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
        ]);
        $this->app->instance(StructuredTextModelProviderInterface::class, new FakeStructuredTextModelProvider([
            'document' => [
                'is_price_list' => true, 'supplier_name' => null, 'supplier_inn' => null,
                'default_currency' => 'RUB', 'default_vat_mode' => 'unknown', 'default_vat_rate' => null,
                'valid_from' => null, 'valid_to' => null, 'notes' => null,
            ],
            'column_mapping' => [],
            'items' => [[
                'source_locator' => ['sheet' => null, 'page' => 3, 'table' => null, 'row' => 8, 'cells' => null],
                'raw_text' => 'Какао порошок — 455 руб',
                'supplier_sku' => null, 'manufacturer_sku' => null, 'barcode' => null,
                'name' => 'Какао порошок', 'manufacturer' => null, 'brand' => null, 'country_of_origin' => null,
                'package_description' => null, 'units_per_package' => null, 'net_quantity' => null,
                'net_quantity_unit' => null, 'price_basis_quantity' => null, 'price_basis_unit' => null,
                'minimum_order_quantity' => null, 'price' => '455.00', 'currency' => 'RUB',
                'vat_mode' => 'unknown', 'vat_rate' => null, 'availability' => null,
                'valid_from' => null, 'valid_to' => null, 'notes' => null,
                'field_evidence' => [['field' => 'price', 'source' => '455 руб', 'confidence' => 0.95]],
                'warnings' => [],
            ]],
            'warnings' => [],
        ]));

        app()->call([new NormalizePriceListRows($import->id), 'handle']);

        $item = $import->items()->where('source_page', 3)->where('source_row', 8)->firstOrFail();
        $this->assertSame('Какао порошок', $item->raw_name);
        $this->assertSame('455.000000', $item->price);
        $this->assertSame(PriceListStatus::ReviewRequired, $import->fresh()->status);
        $this->assertDatabaseHas('ai_usage_records', [
            'price_list_import_id' => $import->id,
            'provider' => 'yandex_ai_studio',
            'status' => 'success',
        ]);
    }

    public function test_truncated_structured_output_is_retried_with_smaller_row_chunks(): void
    {
        config()->set('ai-price-lists.ai.max_rows_per_chunk', 20);
        $import = $this->import(['status' => PriceListStatus::Normalizing, 'current_stage' => 'normalize', 'progress' => 45]);

        foreach ([1, 2] as $position) {
            PriceListImportItem::query()->create([
                'price_list_import_id' => $import->id,
                'position' => $position,
                'source_page' => 1,
                'source_row' => $position,
                'raw_cells' => ["Товар {$position} — 100 RUB"],
                'raw_text' => "Товар {$position} — 100 RUB",
                'row_fingerprint' => hash('sha256', 'adaptive-chunk-row-'.$position),
                'decision_status' => ItemDecisionStatus::Unreviewed,
                'match_class' => MatchClass::None,
            ]);
        }

        $provider = new class implements StructuredTextModelProviderInterface
        {
            public int $calls = 0;

            public function configured(): bool
            {
                return true;
            }

            public function generate(StructuredModelRequest $request): StructuredModelResponse
            {
                $this->calls++;
                $rows = json_decode($request->data, true, 512, JSON_THROW_ON_ERROR)['rows'];

                if (count($rows) > 1) {
                    throw new ExternalAiException(
                        'Ответ оборван.',
                        true,
                        'ai_output_truncated',
                        'truncated-request',
                        [
                            'finish_reason' => 'length',
                            'input_tokens' => 10,
                            'output_tokens' => 12,
                            'total_tokens' => 22,
                            'latency_ms' => 5,
                        ],
                    );
                }

                $row = $rows[0];

                return new StructuredModelResponse(
                    data: [
                        'document' => [
                            'is_price_list' => true,
                            'supplier_name' => null,
                            'supplier_inn' => null,
                            'default_currency' => 'RUB',
                            'default_vat_mode' => 'unknown',
                            'default_vat_rate' => null,
                            'valid_from' => null,
                            'valid_to' => null,
                            'notes' => null,
                        ],
                        'column_mapping' => [],
                        'items' => [[
                            'source_locator' => $row['source_locator'],
                            'raw_text' => $row['raw_text'],
                            'supplier_sku' => null,
                            'manufacturer_sku' => null,
                            'barcode' => null,
                            'name' => 'Товар '.$row['source_locator']['row'],
                            'manufacturer' => null,
                            'brand' => null,
                            'country_of_origin' => null,
                            'package_description' => null,
                            'units_per_package' => null,
                            'net_quantity' => null,
                            'net_quantity_unit' => null,
                            'price_basis_quantity' => null,
                            'price_basis_unit' => null,
                            'minimum_order_quantity' => null,
                            'price' => '100.00',
                            'currency' => 'RUB',
                            'vat_mode' => 'unknown',
                            'vat_rate' => null,
                            'availability' => null,
                            'valid_from' => null,
                            'valid_to' => null,
                            'notes' => null,
                            'field_evidence' => [],
                            'warnings' => [],
                        ]],
                        'warnings' => [],
                    ],
                    model: 'fake-adaptive-model',
                    externalRequestId: 'successful-request-'.$this->calls,
                    inputTokens: 5,
                    outputTokens: 5,
                    totalTokens: 10,
                    latencyMs: 1,
                );
            }
        };
        $this->app->instance(StructuredTextModelProviderInterface::class, $provider);

        app()->call([new NormalizePriceListRows($import->id), 'handle']);

        $this->assertSame(3, $provider->calls);
        $this->assertSame(PriceListStatus::ReviewRequired, $import->fresh()->status);
        $this->assertSame(['Товар 1', 'Товар 2'], $import->items()->pluck('raw_name')->all());
        $this->assertDatabaseHas('ai_usage_records', [
            'price_list_import_id' => $import->id,
            'operation' => 'structured_extraction',
            'status' => 'failed',
            'error_code' => 'ai_output_truncated',
            'total_tokens' => 22,
        ]);
        $this->assertSame(2, $import->usageRecords()->where('operation', 'structured_extraction')->where('status', 'success')->count());
    }

    public function test_fake_ocr_pipeline_records_pages_and_reaches_review(): void
    {
        $import = $this->import([
            'status' => PriceListStatus::Ocr,
            'current_stage' => 'ocr',
            'progress' => 30,
            'path' => 'imports/photo.png',
            'original_name' => 'photo.png',
            'safe_name' => 'photo.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put($import->path, $this->validPng());
        $this->app->instance(OcrProviderInterface::class, new FakeOcrProvider([
            ['page' => 1, 'table' => 1, 'row' => 1, 'text' => 'Наименование | Цена', 'cells' => ['1' => 'Наименование', '2' => 'Цена']],
            ['page' => 1, 'table' => 1, 'row' => 2, 'text' => 'Сливки | 420,00', 'cells' => ['1' => 'Сливки', '2' => '420,00']],
        ]));

        app()->call([new RecognizePriceListWithOcr($import->id), 'handle']);

        $this->assertSame(PriceListStatus::ReviewRequired, $import->fresh()->status);
        $this->assertSame(1, $import->fresh()->ocr_pages);
        $this->assertDatabaseHas('ai_usage_records', ['price_list_import_id' => $import->id, 'provider' => 'yandex_vision', 'pages' => 1]);
        $this->assertDatabaseHas('price_list_import_items', ['price_list_import_id' => $import->id, 'raw_name' => 'Сливки']);
    }

    public function test_retryable_and_permanent_ocr_errors_are_exposed_differently(): void
    {
        foreach ([true, false] as $retryable) {
            $import = $this->import([
                'source_key' => 'ocr-error:'.($retryable ? 'retry' : 'permanent'),
                'status' => PriceListStatus::Ocr,
                'current_stage' => 'ocr',
                'path' => 'imports/error-'.($retryable ? 'retry' : 'permanent').'.png',
                'original_name' => 'error.png',
                'safe_name' => 'error.png',
                'extension' => 'png',
                'mime_type' => 'image/png',
            ]);
            Storage::disk('local')->put($import->path, $this->validPng());
            $this->app->instance(OcrProviderInterface::class, new class($retryable) implements OcrProviderInterface
            {
                public function __construct(private readonly bool $retryable) {}

                public function configured(): bool
                {
                    return true;
                }

                public function recognize(OcrRequest $request): OcrResponse
                {
                    throw new ExternalAiException('Безопасная ошибка OCR.', $this->retryable, $this->retryable ? 'ocr_unavailable' : 'ocr_rejected');
                }
            });

            try {
                app()->call([new RecognizePriceListWithOcr($import->id), 'handle']);
                $this->assertFalse($retryable, 'Retryable exception must be rethrown for the queue.');
            } catch (ExternalAiException) {
                $this->assertTrue($retryable);
            }

            $this->assertSame(PriceListStatus::Failed, $import->fresh()->status);
            $this->assertSame($retryable, $import->fresh()->error_retryable);
        }
    }

    public function test_ai_cannot_create_a_row_without_matching_source_evidence(): void
    {
        $import = $this->import(['status' => PriceListStatus::Normalizing, 'current_stage' => 'normalize']);
        PriceListImportItem::query()->create([
            'price_list_import_id' => $import->id,
            'position' => 1,
            'source_page' => 1,
            'source_row' => 7,
            'raw_cells' => ['text' => 'неоднозначная строка'],
            'raw_text' => 'неоднозначная строка',
            'row_fingerprint' => hash('sha256', 'evidence-row'),
            'decision_status' => ItemDecisionStatus::Unreviewed,
            'match_class' => MatchClass::None,
        ]);
        $response = (new FakeStructuredTextModelProvider)->generate(new \App\Domain\AiPriceLists\DTO\StructuredModelRequest('', '', [], '', '', ''))->data;
        $response['items'] = [[
            'source_locator' => ['sheet' => null, 'page' => 1, 'table' => null, 'row' => 99, 'cells' => null],
            'raw_text' => 'Выдуманный товар 100 RUB',
            'supplier_sku' => null, 'manufacturer_sku' => null, 'barcode' => null,
            'name' => 'Выдуманный товар', 'manufacturer' => null, 'brand' => null, 'country_of_origin' => null,
            'package_description' => null, 'units_per_package' => null, 'net_quantity' => null,
            'net_quantity_unit' => null, 'price_basis_quantity' => null, 'price_basis_unit' => null,
            'minimum_order_quantity' => null, 'price' => '100.00', 'currency' => 'RUB',
            'vat_mode' => 'unknown', 'vat_rate' => null, 'availability' => null,
            'valid_from' => null, 'valid_to' => null, 'notes' => null, 'field_evidence' => [], 'warnings' => [],
        ]];
        $this->app->instance(StructuredTextModelProviderInterface::class, new FakeStructuredTextModelProvider($response));

        app()->call([new NormalizePriceListRows($import->id), 'handle']);

        $this->assertSame(PriceListStatus::Failed, $import->fresh()->status);
        $this->assertSame('ai_evidence_invalid', $import->fresh()->error_code);
        $this->assertDatabaseCount('price_list_import_items', 1);
        $this->assertDatabaseHas('ai_usage_records', ['price_list_import_id' => $import->id, 'status' => 'failed']);
    }

    public function test_ocr_budget_stops_request_before_provider_call(): void
    {
        config()->set('ai-price-lists.ai.daily_ocr_page_limit', 0);
        $import = $this->import([
            'status' => PriceListStatus::Ocr,
            'current_stage' => 'ocr',
            'path' => 'imports/budget.png',
            'original_name' => 'budget.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
        ]);
        Storage::disk('local')->put($import->path, $this->validPng());
        $this->app->instance(OcrProviderInterface::class, new class implements OcrProviderInterface
        {
            public function configured(): bool
            {
                return true;
            }

            public function recognize(OcrRequest $request): OcrResponse
            {
                throw new \LogicException('Provider must not be called when the budget is exhausted.');
            }
        });

        app()->call([new RecognizePriceListWithOcr($import->id), 'handle']);

        $this->assertSame(PriceListStatus::Failed, $import->fresh()->status);
        $this->assertSame('ocr_budget_exceeded', $import->fresh()->error_code);
    }

    private function validPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }
}
