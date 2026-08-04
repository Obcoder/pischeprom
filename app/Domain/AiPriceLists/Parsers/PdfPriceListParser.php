<?php

namespace App\Domain\AiPriceLists\Parsers;

use App\Domain\AiPriceLists\Contracts\PriceListParserInterface;
use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use Smalot\PdfParser\Parser;

class PdfPriceListParser implements PriceListParserInterface
{
    public function supports(string $extension): bool
    {
        return $extension === 'pdf';
    }

    public function parse(string $localPath, string $extension): ExtractionResult
    {
        $document = (new Parser)->parseFile($localPath);
        $pages = $document->getPages();
        $pageLimit = (int) config('ai-price-lists.limits.max_pages');

        if (count($pages) > $pageLimit) {
            throw new \RuntimeException('PDF превышает допустимое количество страниц.');
        }

        $rows = [];
        $textCharacters = 0;
        $rowLimit = (int) config('ai-price-lists.limits.max_rows');
        $ocrPageNumbers = [];

        foreach ($pages as $pageIndex => $page) {
            $text = trim($page->getText());
            $pageCharacters = mb_strlen(preg_replace('/\s+/u', '', $text) ?: '');
            $textCharacters += $pageCharacters;
            $pageRows = [];

            foreach (preg_split('/\R/u', $text) ?: [] as $lineNumber => $line) {
                $line = mb_substr(trim(preg_replace('/\s+/u', ' ', $line) ?: ''), 0, 5000);

                if ($line === '') {
                    continue;
                }

                if (count($rows) + count($pageRows) >= $rowLimit) {
                    throw new \RuntimeException('PDF превышает допустимое количество строк.');
                }

                $cells = array_values(array_filter(preg_split('/\s{2,}|\t/u', $line) ?: [], fn ($value) => trim($value) !== ''));
                $pageRows[] = new ExtractedRow(
                    position: count($rows) + count($pageRows) + 1,
                    cells: array_combine(array_map('strval', range(1, count($cells))), $cells) ?: ['text' => $line],
                    text: $line,
                    page: $pageIndex + 1,
                    row: $lineNumber + 1,
                    evidence: ['parser' => 'smalot-pdfparser-v1'],
                );
            }

            if ($pageCharacters < 20) {
                $ocrPageNumbers[] = $pageIndex + 1;
            } else {
                $rows = [...$rows, ...$pageRows];
            }
        }

        $requiresOcr = $ocrPageNumbers !== [];

        return new ExtractionResult(
            rows: $rows,
            parser: 'smalot-pdfparser-v1',
            requiresOcr: $requiresOcr,
            metadata: [
                'pages' => count($pages),
                'text_characters' => $textCharacters,
                'ocr_page_numbers' => $ocrPageNumbers,
            ],
        );
    }
}
