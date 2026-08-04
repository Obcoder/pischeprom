<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\PriceListParserInterface;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use App\Domain\AiPriceLists\Parsers\CsvPriceListParser;
use App\Domain\AiPriceLists\Parsers\DocxPriceListParser;
use App\Domain\AiPriceLists\Parsers\PdfPriceListParser;
use App\Domain\AiPriceLists\Parsers\SpreadsheetPriceListParser;
use RuntimeException;

class PriceListParserManager
{
    /** @var list<PriceListParserInterface> */
    private array $parsers;

    public function __construct(
        SpreadsheetPriceListParser $spreadsheets,
        CsvPriceListParser $csv,
        DocxPriceListParser $docx,
        PdfPriceListParser $pdf,
    ) {
        $this->parsers = [$spreadsheets, $csv, $docx, $pdf];
    }

    public function parse(string $localPath, string $extension): ExtractionResult
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($extension)) {
                return $parser->parse($localPath, $extension);
            }
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic'], true)) {
            return new ExtractionResult([], 'vision-required', requiresOcr: true);
        }

        throw new RuntimeException('Для формата документа не найден безопасный parser.');
    }
}
