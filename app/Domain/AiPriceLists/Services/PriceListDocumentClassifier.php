<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\DTO\ExtractionResult;
use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;

class PriceListDocumentClassifier
{
    private const CANDIDATE_EXTENSIONS = [
        'xlsx', 'xls', 'csv', 'tsv', 'docx', 'doc', 'pdf',
        'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic',
    ];

    private const PRICE_WORDS = [
        'прайс', 'price', 'цены', 'цена', 'стоимость', 'опт', 'ассортимент',
        'предложение', 'catalog', 'каталог', 'товары',
    ];

    public function eligibleMailAttachment(MailMessageAttachment $attachment, MailMessage $message): bool
    {
        $extension = strtolower(pathinfo($attachment->original_name ?: $attachment->file_name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::CANDIDATE_EXTENSIONS, true)) {
            return false;
        }

        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic'], true);

        if ($isImage && (strtolower((string) $attachment->disposition) === 'inline' || filled($attachment->content_id))) {
            return false;
        }

        if ($isImage && (int) $attachment->size < 10 * 1024 && ! $this->hasPriceSignal($attachment->original_name, $message->subject)) {
            return false;
        }

        return true;
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
