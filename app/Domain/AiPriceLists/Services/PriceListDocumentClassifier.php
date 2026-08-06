<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\DTO\ExtractionResult;
use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;

class PriceListDocumentClassifier
{
    private const CANDIDATE_EXTENSIONS = [
        'xlsx', 'xls', 'csv', 'tsv', 'docx', 'pdf',
        'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic',
    ];

    private const TABULAR_EXTENSIONS = ['xlsx', 'xls', 'csv', 'tsv'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic'];

    private const PRICE_WORDS = [
        'прайс', 'price', 'цены', 'цена', 'стоимость', 'опт', 'ассортимент',
        'предложение', 'catalog', 'каталог', 'товары',
    ];

    public function eligibleMailAttachment(MailMessageAttachment $attachment, MailMessage $message): bool
    {
        return $this->mailAttachmentRejectionReason([
            'original_name' => $attachment->original_name,
            'file_name' => $attachment->file_name,
            'size' => $attachment->size,
            'content_id' => $attachment->content_id,
            'disposition' => $attachment->disposition,
        ], $message) === null;
    }

    /**
     * @param  array{original_name?: mixed, file_name?: mixed, size?: mixed, content_id?: mixed, disposition?: mixed}  $attachment
     */
    public function eligibleMailAttachmentMetadata(array $attachment, MailMessage $message): bool
    {
        return $this->mailAttachmentRejectionReason($attachment, $message) === null;
    }

    /**
     * @param  array{original_name?: mixed, file_name?: mixed, size?: mixed, content_id?: mixed, disposition?: mixed}  $attachment
     */
    public function automaticMailAttachmentRejectionReason(array $attachment, MailMessage $message): ?string
    {
        $reason = $this->mailAttachmentRejectionReason($attachment, $message);

        if ($reason !== null) {
            return $reason;
        }

        $name = trim((string) ($attachment['original_name'] ?? $attachment['file_name'] ?? ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, self::TABULAR_EXTENSIONS, true)) {
            return null;
        }

        if ($this->hasPriceSignal($name, $message->subject, $message->preview)) {
            return null;
        }

        return in_array($extension, self::IMAGE_EXTENSIONS, true)
            ? 'image_without_price_signal'
            : 'document_without_price_signal';
    }

    /**
     * @param  array{original_name?: mixed, file_name?: mixed, size?: mixed, content_id?: mixed, disposition?: mixed}  $attachment
     */
    public function mailAttachmentRejectionReason(array $attachment, MailMessage $message): ?string
    {
        $name = trim((string) ($attachment['original_name'] ?? $attachment['file_name'] ?? ''));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::CANDIDATE_EXTENSIONS, true)) {
            return 'unsupported_extension';
        }

        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);

        if ($isImage && (strtolower((string) ($attachment['disposition'] ?? '')) === 'inline' || filled($attachment['content_id'] ?? null))) {
            return 'inline_image';
        }

        if ($isImage && (int) ($attachment['size'] ?? 0) < 10 * 1024 && ! $this->hasPriceSignal($name, $message->subject)) {
            return 'small_image_without_price_signal';
        }

        return null;
    }

    public function classify(string $fileName, ?string $subject = null, ?string $caption = null): DocumentClass
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $signal = $this->hasPriceSignal($fileName, $subject, $caption);

        if ($signal) {
            return DocumentClass::PriceList;
        }

        if (in_array($extension, ['xlsx', 'xls', 'csv', 'tsv'], true)) {
            return DocumentClass::Uncertain;
        }

        return DocumentClass::Uncertain;
    }

    public function hasPriceSignal(?string ...$values): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter($values, fn ($value) => filled($value))));

        if (preg_match('/(?:^|[^\p{L}\p{N}])кп(?:$|[^\p{L}\p{N}])/u', $haystack) === 1) {
            return true;
        }

        foreach (self::PRICE_WORDS as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    public function classifyExtraction(ExtractionResult $result): DocumentClass
    {
        $sample = collect($result->rows)->take(40)->pluck('text')->implode("\n");
        $normalized = mb_strtolower($sample);
        $hasNameHeader = preg_match('/наименование|название|товар|продукция|product|name/iu', $normalized) === 1;
        $hasPriceHeader = preg_match('/цена|стоимость|прайс|price|cost/iu', $normalized) === 1;
        $hasMoneyRows = preg_match_all('/\d[\d\s]*(?:[,.]\d{1,2})?\s*(?:₽|руб|rub|usd|eur|€|\$)?/iu', $sample) >= 3;

        return ($hasNameHeader && $hasPriceHeader) || ($this->hasPriceSignal($sample) && $hasMoneyRows)
            ? DocumentClass::PriceList
            : DocumentClass::Uncertain;
    }
}
