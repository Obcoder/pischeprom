<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\MatchClass;
use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Services\AiUsageRecorder;
use App\Domain\AiPriceLists\Services\OcrInputPreparer;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Domain\AiPriceLists\Services\StoredFileMaterializer;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use Illuminate\Queue\Middleware\RateLimited;

class RecognizePriceListWithOcr extends AbstractPriceListJob
{
    public function middleware(): array
    {
        return [...parent::middleware(), (new RateLimited('price-list-ai'))->releaseAfter(30)];
    }

    public function handle(
        OcrProviderInterface $ocr,
        StoredFileMaterializer $files,
        OcrInputPreparer $preparer,
        AiUsageRecorder $usage,
        PriceListStateMachine $states,
    ): void {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if (! in_array($import->status, [PriceListStatus::Ocr, PriceListStatus::Failed], true)) {
            return;
        }

        $import = $states->transition($import, PriceListStatus::Ocr, PriceListStage::Ocr, 35);

        if ((int) $import->size_bytes > (int) config('ai-price-lists.limits.max_ocr_file_bytes')) {
            $states->fail($import, 'ocr_file_too_large', 'Файл превышает безопасный лимит отправки в OCR.', false);

            return;
        }

        if ((int) data_get($import->document_metadata, 'pages', 0) > (int) config('ai-price-lists.limits.max_ocr_pages')) {
            $states->fail($import, 'ocr_page_limit', 'Документ превышает лимит OCR-страниц.', false);

            return;
        }

        try {
            $recognized = $files->using($import->disk, $import->path, function (string $path) use ($import, $ocr, $preparer, $usage): array {
                $rows = [];
                $processedPages = 0;
                $selectedPages = array_values(array_map('intval', (array) data_get($import->document_metadata, 'ocr_page_numbers', [])));
                $totalPages = data_get($import->document_metadata, 'pages');

                foreach ($preparer->requests(
                    $path,
                    (string) $import->mime_type,
                    $import->original_name,
                    $selectedPages,
                    is_numeric($totalPages) ? (int) $totalPages : null,
                ) as $prepared) {
                    $usage->guardOcrBudget($prepared['expected_pages']);
                    $response = $ocr->recognize($prepared['request']);
                    $usage->ocr($import, $response);
                    $processedPages += $response->pages;

                    if ($processedPages > (int) config('ai-price-lists.limits.max_ocr_pages')) {
                        throw new ExternalAiException('Документ превышает лимит OCR-страниц.', false, 'ocr_page_limit');
                    }

                    foreach ($response->rows as $row) {
                        if ($prepared['source_page'] !== null) {
                            $row['page'] = $prepared['source_page'];
                        }

                        $rows[] = $row;

                        if (count($rows) > (int) config('ai-price-lists.limits.max_rows')) {
                            throw new ExternalAiException('OCR вернул больше допустимого количества строк.', false, 'ocr_row_limit');
                        }
                    }

                    $import->forceFill(['stage_heartbeat_at' => now()])->save();
                }

                return ['rows' => $rows, 'pages' => $processedPages];
            });
        } catch (ExternalAiException $exception) {
            $usage->failure($import, 'yandex_vision', 'ocr', $exception, (string) config('ai-price-lists.ocr.model'));
            $states->fail($import, $exception->errorCode, $exception->getMessage(), $exception->retryable, ['external_request_id' => $exception->externalRequestId]);

            if ($exception->retryable) {
                throw $exception;
            }

            return;
        }

        if ($recognized['rows'] === []) {
            $states->fail($import, 'ocr_empty_result', 'OCR не обнаружил в документе текстовых строк.', false);

            return;
        }

        $position = (int) $import->items()->max('position');

        foreach ($recognized['rows'] as $row) {
            $position++;
            $fingerprint = hash('sha256', json_encode([$row['page'] ?? null, $row['table'] ?? null, $row['row'] ?? null, $row['text']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            PriceListImportItem::query()->updateOrCreate([
                'price_list_import_id' => $import->id,
                'row_fingerprint' => $fingerprint,
            ], [
                'position' => $position,
                'source_page' => $row['page'] ?? null,
                'source_table' => $row['table'] ?? null,
                'source_row' => $row['row'] ?? null,
                'raw_cells' => $row['cells'] ?? ['text' => $row['text']],
                'raw_text' => mb_substr((string) $row['text'], 0, 10000),
                'field_evidence' => ['ocr_bounding_box' => $row['bounding_box'] ?? null],
                'decision_status' => ItemDecisionStatus::Unreviewed,
                'match_class' => MatchClass::None,
            ]);
        }

        $import->forceFill(['ocr_pages' => $recognized['pages']])->save();
        $states->transition($import, PriceListStatus::Normalizing, PriceListStage::Normalize, 55);
        $this->dispatchNext(new NormalizePriceListRows($import->id));
    }
}
