<?php

namespace App\Domain\AiPriceLists\Parsers;

use App\Domain\AiPriceLists\Contracts\PriceListParserInterface;
use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class DocxPriceListParser implements PriceListParserInterface
{
    public function supports(string $extension): bool
    {
        return $extension === 'docx';
    }

    public function parse(string $localPath, string $extension): ExtractionResult
    {
        $document = IOFactory::load($localPath, 'Word2007');
        $tableRows = [];
        $paragraphs = [];
        $tableNumber = 0;
        $position = 0;
        $limit = (int) config('ai-price-lists.limits.max_rows');
        $columnLimit = (int) config('ai-price-lists.limits.max_columns');

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    $tableNumber++;

                    foreach ($element->getRows() as $rowNumber => $row) {
                        if (count($tableRows) >= $limit) {
                            throw new RuntimeException('DOCX превышает допустимое количество строк.');
                        }

                        if (count($row->getCells()) > $columnLimit) {
                            throw new RuntimeException('DOCX превышает допустимое количество колонок.');
                        }

                        $cells = [];

                        foreach ($row->getCells() as $cellNumber => $cell) {
                            $text = mb_substr($this->elementText($cell), 0, 5000);

                            if ($text !== '') {
                                $cells[(string) ($cellNumber + 1)] = $text;
                            }
                        }

                        if ($cells === []) {
                            continue;
                        }

                        $position++;
                        $tableRows[] = new ExtractedRow(
                            position: $position,
                            cells: $cells,
                            text: implode(' | ', $cells),
                            table: $tableNumber,
                            row: $rowNumber + 1,
                            evidence: ['parser' => 'phpword-v1'],
                        );
                    }
                } else {
                    $text = $this->elementText($element);

                    if ($text !== '') {
                        if (count($paragraphs) >= $limit) {
                            throw new RuntimeException('DOCX превышает допустимое количество строк.');
                        }

                        $paragraphs[] = mb_substr($text, 0, 5000);
                    }
                }
            }
        }

        if ($tableRows !== []) {
            return new ExtractionResult($tableRows, 'phpword-v1', metadata: ['tables' => $tableNumber]);
        }

        $rows = [];

        foreach ($paragraphs as $index => $text) {
            $rows[] = new ExtractedRow($index + 1, ['text' => $text], $text, row: $index + 1, evidence: ['parser' => 'phpword-v1']);
        }

        return new ExtractionResult($rows, 'phpword-v1', metadata: ['tables' => 0]);
    }

    private function elementText(object $element): string
    {
        if (method_exists($element, 'getElements')) {
            $parts = array_map(fn (object $child): string => $this->elementText($child), $element->getElements());

            return trim(implode(' ', array_filter($parts)));
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_scalar($text) ? trim((string) $text) : '';
        }

        return '';
    }
}
