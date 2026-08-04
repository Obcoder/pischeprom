<?php

namespace App\Domain\AiPriceLists\Parsers;

use App\Domain\AiPriceLists\Contracts\PriceListParserInterface;
use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SpreadsheetPriceListParser implements PriceListParserInterface
{
    public function supports(string $extension): bool
    {
        return in_array($extension, ['xlsx', 'xls'], true);
    }

    public function parse(string $localPath, string $extension): ExtractionResult
    {
        $reader = IOFactory::createReaderForFile($localPath);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $sheetLimit = (int) config('ai-price-lists.limits.max_sheets');
        $rowLimit = (int) config('ai-price-lists.limits.max_rows');
        $columnLimit = (int) config('ai-price-lists.limits.max_columns');
        $worksheetInfo = $reader->listWorksheetInfo($localPath);

        if (count($worksheetInfo) > $sheetLimit) {
            throw new RuntimeException('В книге больше допустимого количества листов.');
        }

        $declaredRows = 0;

        foreach ($worksheetInfo as $info) {
            $declaredRows += (int) ($info['totalRows'] ?? 0);

            if ($declaredRows > $rowLimit) {
                throw new RuntimeException('Книга превышает допустимое количество строк.');
            }

            if ((int) ($info['totalColumns'] ?? 0) > $columnLimit) {
                throw new RuntimeException('Книга превышает допустимое количество колонок.');
            }
        }

        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly(array_column($worksheetInfo, 'worksheetName'));
        }

        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }

        $spreadsheet = $reader->load($localPath);

        try {
            $sheets = iterator_to_array($spreadsheet->getWorksheetIterator(), false);
            $rows = [];
            $position = 0;
            $sheetMetadata = [];

            foreach ($sheets as $worksheet) {
                $highestRow = $worksheet->getHighestDataRow();
                $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
                $sheetMetadata[] = [
                    'name' => $worksheet->getTitle(),
                    'merged_ranges' => array_values($worksheet->getMergeCells()),
                ];

                for ($rowNumber = 1; $rowNumber <= $highestRow && count($rows) < $rowLimit; $rowNumber++) {
                    $cells = [];
                    $formulaCells = [];

                    for ($column = 1; $column <= $highestColumnIndex; $column++) {
                        $cell = $worksheet->getCell([$column, $rowNumber]);
                        $isFormula = $cell->getDataType() === DataType::TYPE_FORMULA;
                        $value = $isFormula ? $cell->getOldCalculatedValue() : $cell->getValue();

                        if ($isFormula) {
                            $formulaCells[] = $cell->getCoordinate();
                        }

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $cells[$cell->getColumn()] = is_scalar($value) ? mb_substr(trim((string) $value), 0, 5000) : null;
                    }

                    $cells = array_filter($cells, fn ($value) => $value !== null && $value !== '');

                    if ($cells === []) {
                        continue;
                    }

                    $position++;
                    $rows[] = new ExtractedRow(
                        position: $position,
                        cells: $cells,
                        text: implode(' | ', $cells),
                        sheet: $worksheet->getTitle(),
                        row: $rowNumber,
                        range: array_key_first($cells).$rowNumber.':'.array_key_last($cells).$rowNumber,
                        evidence: [
                            'parser' => 'phpspreadsheet',
                            'formulas_not_executed' => true,
                            'formula_cells' => $formulaCells,
                        ],
                    );
                }
            }

            return new ExtractionResult(
                rows: $rows,
                parser: 'phpspreadsheet-v1',
                metadata: ['sheets' => $sheetMetadata],
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
