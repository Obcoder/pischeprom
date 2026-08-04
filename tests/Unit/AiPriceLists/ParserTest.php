<?php

namespace Tests\Unit\AiPriceLists;

use App\Domain\AiPriceLists\Parsers\CsvPriceListParser;
use App\Domain\AiPriceLists\Parsers\DocxPriceListParser;
use App\Domain\AiPriceLists\Parsers\PdfPriceListParser;
use App\Domain\AiPriceLists\Parsers\SpreadsheetPriceListParser;
use Tests\Support\AiPriceListFixtureFactory;
use Tests\TestCase;

class ParserTest extends TestCase
{
    private string $directory;

    private AiPriceListFixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/pischeprom-ai-fixtures-'.bin2hex(random_bytes(6));
        $this->fixtures = new AiPriceListFixtureFactory($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_csv_encodings_and_delimiters_are_parsed_with_source_rows(): void
    {
        $parser = app(CsvPriceListParser::class);
        $utf8 = $parser->parse($this->fixtures->csvUtf8(), 'csv');
        $cp1251 = $parser->parse($this->fixtures->csvWindows1251(), 'csv');

        $this->assertCount(3, $utf8->rows);
        $this->assertStringContainsString('Мука', $utf8->rows[1]->text);
        $this->assertSame(2, $utf8->rows[1]->row);
        $this->assertStringContainsString('Крупа', $cp1251->rows[1]->text);
    }

    public function test_spreadsheets_include_multiple_sheets_and_do_not_calculate_formulas(): void
    {
        $parser = app(SpreadsheetPriceListParser::class);
        $xlsx = $parser->parse($this->fixtures->xlsx(true), 'xlsx');
        $xls = $parser->parse($this->fixtures->xlsWithFormula(), 'xls');

        $this->assertContains('Основной', array_column($xlsx->metadata['sheets'], 'name'));
        $this->assertContains('Регион', array_column($xlsx->metadata['sheets'], 'name'));
        $this->assertGreaterThanOrEqual(6, count($xlsx->rows));
        $this->assertNotEmpty($xls->rows);
        $this->assertContains('B3', collect($xls->rows)->flatMap(fn ($row) => $row->evidence['formula_cells'] ?? [])->all());
        $this->assertSame('phpspreadsheet-v1', $xlsx->parser);
    }

    public function test_tabular_parsers_reject_dimensions_beyond_configured_limits(): void
    {
        config()->set('ai-price-lists.limits.max_columns', 1);

        foreach ([
            fn () => app(SpreadsheetPriceListParser::class)->parse($this->fixtures->xlsx(), 'xlsx'),
            fn () => app(CsvPriceListParser::class)->parse($this->fixtures->csvUtf8(), 'csv'),
            fn () => app(DocxPriceListParser::class)->parse($this->fixtures->docx(), 'docx'),
        ] as $parse) {
            try {
                $parse();
                $this->fail('Parser must reject a document wider than the configured limit.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('колонок', $exception->getMessage());
            }
        }
    }

    public function test_docx_tables_and_text_pdf_are_parsed_locally(): void
    {
        $docx = app(DocxPriceListParser::class)->parse($this->fixtures->docx(), 'docx');
        $pdf = app(PdfPriceListParser::class)->parse($this->fixtures->textPdf(), 'pdf');

        $this->assertGreaterThanOrEqual(3, count($docx->rows));
        $this->assertFalse($docx->requiresOcr);
        $this->assertStringContainsString('price list', collect($pdf->rows)->pluck('text')->implode(' '));
        $this->assertFalse($pdf->requiresOcr);
    }

    public function test_scanned_and_mixed_pdfs_request_ocr_only_for_pages_without_text(): void
    {
        $parser = app(PdfPriceListParser::class);
        $scanned = $parser->parse($this->fixtures->scannedPdf(), 'pdf');
        $mixed = $parser->parse($this->fixtures->mixedPdf(), 'pdf');

        $this->assertTrue($scanned->requiresOcr);
        $this->assertSame([1], $scanned->metadata['ocr_page_numbers']);
        $this->assertEmpty($scanned->rows);

        $this->assertTrue($mixed->requiresOcr);
        $this->assertSame([2], $mixed->metadata['ocr_page_numbers']);
        $this->assertNotEmpty($mixed->rows);
        $this->assertStringContainsString('text first page', collect($mixed->rows)->pluck('text')->implode(' '));
    }
}
