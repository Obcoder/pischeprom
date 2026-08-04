<?php

namespace App\Domain\AiPriceLists\Parsers;

use App\Domain\AiPriceLists\Contracts\PriceListParserInterface;
use App\Domain\AiPriceLists\DTO\ExtractedRow;
use App\Domain\AiPriceLists\DTO\ExtractionResult;
use RuntimeException;

class CsvPriceListParser implements PriceListParserInterface
{
    public function supports(string $extension): bool
    {
        return in_array($extension, ['csv', 'tsv'], true);
    }

    public function parse(string $localPath, string $extension): ExtractionResult
    {
        $sample = (string) file_get_contents($localPath, false, null, 0, 65536);
        $encoding = mb_detect_encoding($sample, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true) ?: 'Windows-1251';
        $utf8Sample = $encoding === 'UTF-8' ? $sample : mb_convert_encoding($sample, 'UTF-8', $encoding);
        $delimiter = $extension === 'tsv' ? "\t" : $this->detectDelimiter($utf8Sample);
        $handle = fopen($localPath, 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException('CSV-файл недоступен для чтения.');
        }

        try {
            $rows = [];
            $sourceRow = 0;
            $limit = (int) config('ai-price-lists.limits.max_rows');
            $columnLimit = (int) config('ai-price-lists.limits.max_columns');

            while (($values = fgetcsv($handle, 1024 * 1024, $delimiter, '"', '\\')) !== false) {
                $sourceRow++;

                if ($sourceRow > $limit) {
                    throw new RuntimeException('CSV превышает допустимое количество строк.');
                }

                if (count($values) > $columnLimit) {
                    throw new RuntimeException('CSV превышает допустимое количество колонок.');
                }

                $values = array_slice($values, 0, $columnLimit);
                $cells = [];

                foreach ($values as $index => $value) {
                    if ($encoding !== 'UTF-8') {
                        $value = mb_convert_encoding((string) $value, 'UTF-8', $encoding);
                    }

                    $value = mb_substr(trim((string) $value), 0, 5000);

                    if ($value !== '') {
                        $cells[(string) ($index + 1)] = $value;
                    }
                }

                if ($cells === []) {
                    continue;
                }

                $rows[] = new ExtractedRow(
                    position: count($rows) + 1,
                    cells: $cells,
                    text: implode(' | ', $cells),
                    sheet: 'CSV',
                    row: $sourceRow,
                    evidence: ['parser' => 'csv-v1', 'encoding' => $encoding, 'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter],
                );
            }

            return new ExtractionResult(
                rows: $rows,
                parser: 'csv-v1',
                metadata: ['encoding' => $encoding, 'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter],
            );
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $sample): string
    {
        $lines = array_slice(array_values(array_filter(preg_split('/\R/u', $sample) ?: [], fn ($line) => trim($line) !== '')), 0, 12);
        $scores = [];

        foreach (["\t", ';', ','] as $delimiter) {
            $counts = array_map(fn (string $line): int => count(str_getcsv($line, $delimiter, '"', '\\')), $lines);
            $scores[$delimiter] = $counts === [] ? 0 : (max($counts) > 1 ? array_sum($counts) / count($counts) : 0);
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }
}
