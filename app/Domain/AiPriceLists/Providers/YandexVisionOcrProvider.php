<?php

namespace App\Domain\AiPriceLists\Providers;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\DTO\OcrResponse;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YandexVisionOcrProvider implements OcrProviderInterface
{
    public function configured(): bool
    {
        return filled(config('ai-price-lists.ai.api_key')) && filled(config('ai-price-lists.ai.folder_id'));
    }

    public function recognize(OcrRequest $request): OcrResponse
    {
        if (! $this->configured()) {
            throw new ExternalAiException('Yandex Vision OCR не настроен.', false, 'ocr_not_configured');
        }

        $started = hrtime(true);
        $clientRequestId = (string) Str::uuid();
        $response = Http::asJson()
            ->acceptJson()
            ->timeout((int) config('ai-price-lists.limits.timeout_seconds'))
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => 'Api-Key '.config('ai-price-lists.ai.api_key'),
                'x-folder-id' => (string) config('ai-price-lists.ai.folder_id'),
                'x-data-logging-enabled' => 'false',
                'x-client-request-id' => $clientRequestId,
            ])
            ->retry(
                (int) config('ai-price-lists.limits.max_attempts'),
                fn (int $attempt) => min(8000, 250 * (2 ** ($attempt - 1)) + random_int(0, 250)),
                static function (\Throwable $exception, PendingRequest $request): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof RequestException || ! $exception->response) {
                        return false;
                    }

                    return $exception->response->status() === 429 || $exception->response->serverError();
                },
                throw: false,
            )
            ->post((string) config('ai-price-lists.ocr.endpoint'), [
                'mimeType' => $this->visionMime($request->mimeType, $request->fileName),
                'languageCodes' => config('ai-price-lists.ocr.language_codes'),
                'model' => config('ai-price-lists.ocr.model'),
                'content' => base64_encode($request->content),
            ]);

        $requestId = $response->header('x-request-id') ?: $clientRequestId;

        if ($response->failed()) {
            throw $this->exceptionFor($response, $requestId);
        }

        $pages = data_get($response->json(), 'result.textAnnotation.pages', []);
        $rows = [];

        foreach (is_array($pages) ? $pages : [] as $pageIndex => $page) {
            foreach ((array) data_get($page, 'blocks', []) as $blockIndex => $block) {
                foreach ((array) data_get($block, 'lines', []) as $lineIndex => $line) {
                    $text = trim((string) data_get($line, 'text'));

                    if ($text === '') {
                        $text = trim(collect((array) data_get($line, 'words', []))->pluck('text')->implode(' '));
                    }

                    if ($text !== '') {
                        $rows[] = [
                            'page' => $pageIndex + 1,
                            'table' => $blockIndex + 1,
                            'row' => $lineIndex + 1,
                            'text' => $text,
                            'cells' => ['text' => $text],
                            'bounding_box' => data_get($line, 'boundingBox'),
                        ];

                        if (count($rows) > (int) config('ai-price-lists.limits.max_rows')) {
                            throw new ExternalAiException('OCR вернул больше допустимого количества строк.', false, 'ocr_row_limit', $requestId);
                        }
                    }
                }
            }
        }

        if ($rows === []) {
            $fullText = trim((string) data_get($response->json(), 'result.textAnnotation.fullText'));

            foreach (preg_split('/\R/u', $fullText) ?: [] as $index => $text) {
                if (trim($text) !== '') {
                    $rows[] = ['page' => 1, 'row' => $index + 1, 'text' => trim($text), 'cells' => ['text' => trim($text)]];

                    if (count($rows) > (int) config('ai-price-lists.limits.max_rows')) {
                        throw new ExternalAiException('OCR вернул больше допустимого количества строк.', false, 'ocr_row_limit', $requestId);
                    }
                }
            }
        }

        $pageCount = max(1, count(is_array($pages) ? $pages : []));

        if ($pageCount > (int) config('ai-price-lists.limits.max_ocr_pages')) {
            throw new ExternalAiException('Документ превышает лимит OCR-страниц.', false, 'ocr_page_limit', $requestId);
        }

        return new OcrResponse(
            rows: $rows,
            pages: $pageCount,
            externalRequestId: $requestId,
            latencyMs: (int) round((hrtime(true) - $started) / 1_000_000),
            metadata: ['model' => config('ai-price-lists.ocr.model')],
        );
    }

    private function visionMime(string $mime, string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'PDF',
            'png' => 'PNG',
            'jpg', 'jpeg' => 'JPEG',
            default => throw new ExternalAiException('Формат не подготовлен для Yandex Vision OCR.', false, 'ocr_unsupported_mime'),
        };
    }

    private function exceptionFor(Response $response, string $requestId): ExternalAiException
    {
        $status = $response->status();
        $retryable = $status === 429 || $status >= 500;

        return new ExternalAiException(
            $retryable ? 'OCR-сервис временно недоступен.' : 'OCR-сервис отклонил документ.',
            $retryable,
            $status === 429 ? 'ocr_rate_limited' : ($status >= 500 ? 'ocr_unavailable' : 'ocr_rejected'),
            $requestId,
        );
    }
}
