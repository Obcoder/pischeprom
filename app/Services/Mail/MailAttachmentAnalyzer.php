<?php

namespace App\Services\Mail;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Providers\FakeOcrProvider;
use App\Domain\AiPriceLists\Services\AiUsageRecorder;
use App\Models\AiUsageRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class MailAttachmentAnalyzer
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_PAGES = 20;

    // The configured Yandex recognizeText endpoint accepts single-page PDFs.
    // Multipage PDFs require its separate asynchronous recognition API.
    private const MAX_OCR_PAGES = 1;

    private const MAX_TEXT_CHARACTERS = 100_000;

    public function __construct(
        private readonly MailInvoiceParser $invoices,
        private readonly OcrProviderInterface $ocr,
        private readonly AiUsageRecorder $usage,
    ) {}

    public function analyze(string $content, bool $recognizeScan = false, int $mailMessageId = 0): array
    {
        if (strlen($content) > self::MAX_FILE_BYTES) {
            throw new MailAttachmentAnalysisException('Для распознавания выберите PDF размером до 10 МБ.');
        }

        if (! str_starts_with($content, '%PDF-')) {
            throw new MailAttachmentAnalysisException('Распознавание доступно только для PDF-файлов.');
        }

        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(16 * 1024 * 1024);

        try {
            $pages = (new Parser([], $config))->parseContent($content)->getPages();

            if (count($pages) < 1 || count($pages) > self::MAX_PAGES) {
                throw new MailAttachmentAnalysisException('Для распознавания выберите PDF от 1 до 20 страниц.');
            }

            $parts = [];
            $characters = 0;

            foreach ($pages as $page) {
                $part = $this->cleanText($page->getText());
                $characters += mb_strlen($part);

                if ($characters > self::MAX_TEXT_CHARACTERS) {
                    throw new MailAttachmentAnalysisException('В PDF слишком много текста для быстрого распознавания.');
                }

                $parts[] = $part;
            }

            $text = trim(implode("\n\n", $parts));
        } catch (MailAttachmentAnalysisException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MailAttachmentAnalysisException('Не удалось разобрать PDF. Возможно, файл повреждён или защищён паролем.');
        }

        $pageCount = count($pages);
        $ocrMessage = $this->ocrUnavailableMessage($pageCount, strlen($content));
        $ocrAvailable = $ocrMessage === null;
        $source = 'text';

        if ($recognizeScan) {
            if (! $ocrAvailable) {
                throw new MailAttachmentAnalysisException($ocrMessage);
            }

            $text = $this->recognizeScan($content, $pageCount, $mailMessageId);
            $source = 'ocr';
        }

        $invoice = $this->invoices->parse($text);
        $hasText = preg_match('/[\p{L}\p{N}]/u', $text) === 1;

        return [
            'status' => ! $hasText ? 'no_text' : ($invoice['is_invoice'] ? 'invoice' : 'not_invoice'),
            'fields' => [
                'number' => $invoice['number'],
                'date' => $invoice['date'],
                'counterparty' => $invoice['counterparty'],
                'heading' => $invoice['heading'],
            ],
            'text' => $text,
            'pages' => $pageCount,
            'source' => $source,
            'ocr_available' => $ocrAvailable,
            'ocr_message' => $ocrMessage,
        ];
    }

    private function ocrUnavailableMessage(int $pageCount, int $bytes): ?string
    {
        $pageLimit = min(self::MAX_OCR_PAGES, (int) config('ai-price-lists.limits.max_ocr_pages', self::MAX_OCR_PAGES));

        if ($pageCount > $pageLimit) {
            return 'Для распознавания сканов поддерживаются одностраничные PDF.';
        }

        if ($bytes > (int) config('ai-price-lists.limits.max_ocr_file_bytes', self::MAX_FILE_BYTES)) {
            return 'Этот PDF превышает настроенный лимит размера для распознавания сканов.';
        }

        if (($this->ocr instanceof FakeOcrProvider && ! app()->environment('testing'))
            || ! $this->ocr->configured()
            || ! Schema::hasTable('ai_usage_records')) {
            return 'Сервис распознавания сканов пока не подключён.';
        }

        return null;
    }

    private function recognizeScan(string $content, int $pageCount, int $mailMessageId): string
    {
        $cacheKey = 'mail-attachment-ocr:v1:'.$mailMessageId.':'.hash('sha256', $content);
        $cached = $this->cachedOcrText($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock('mail-attachment-ocr-budget', 600);

        if (! $lock->get()) {
            throw new MailAttachmentAnalysisException('Сервис OCR обрабатывает другой файл. Повторите через несколько секунд.', 429);
        }

        try {
            $cached = $this->cachedOcrText($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            $this->usage->guardOcrBudget($pageCount);
            $response = $this->ocr->recognize(new OcrRequest($content, 'application/pdf', 'mail-attachment.pdf'));

            // The shared ledger counts these pages against the existing OCR quotas.
            // It stores usage only, without attachment contents or supplier details.
            AiUsageRecord::query()->create([
                'provider' => 'yandex_vision',
                'operation' => 'ocr',
                'model' => config('ai-price-lists.ocr.model'),
                'external_request_id' => $response->externalRequestId,
                'pages' => max($pageCount, $response->pages),
                'units' => $response->pages,
                'latency_ms' => $response->latencyMs,
                'status' => 'success',
                'cost_is_estimate' => true,
                'metadata' => ['source' => 'mail_attachment'],
            ]);

            $text = '';

            foreach ($response->rows as $row) {
                $text .= $this->cleanText((string) ($row['text'] ?? ''))."\n";

                if (mb_strlen($text) > self::MAX_TEXT_CHARACTERS) {
                    throw new MailAttachmentAnalysisException('OCR вернул слишком много текста для быстрого распознавания.');
                }
            }

            $text = trim($text);
            // Short-lived and encrypted: reopening the same scoped document avoids a second charge.
            Cache::put($cacheKey, Crypt::encryptString($text), now()->addMinutes(5));

            return $text;
        } catch (ExternalAiException $exception) {
            throw new MailAttachmentAnalysisException(
                $exception->errorCode === 'ocr_budget_exceeded'
                    ? 'Лимит распознавания сканов исчерпан. Попробуйте позже.'
                    : 'Сервис OCR не смог распознать файл. Попробуйте позже.',
                $exception->errorCode === 'ocr_budget_exceeded' ? 429 : 503,
            );
        } finally {
            $lock->release();
        }
    }

    private function cachedOcrText(string $key): ?string
    {
        $cached = Cache::get($key);

        if (! is_string($cached)) {
            return null;
        }

        try {
            return Crypt::decryptString($cached);
        } catch (Throwable) {
            Cache::forget($key);

            return null;
        }
    }

    private function cleanText(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);

        return trim(preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '');
    }
}
