<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;

class AiPriceListFixtureFactory
{
    public function __construct(public readonly string $directory)
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }

    public function csvUtf8(string $name = 'price-list.csv'): string
    {
        return $this->write($name, "Наименование;Артикул;Цена;Валюта;НДС;Упаковка\nМука пшеничная;00017;1 250,50;RUB;с НДС 20%;20×500 г\nСахар;00018;980.00;RUB;без НДС;кор. 12 шт.\n");
    }

    public function csvWindows1251(string $name = 'price-list-cp1251.csv'): string
    {
        return $this->write($name, mb_convert_encoding("Наименование;Цена\nКрупа;75,20\n", 'Windows-1251', 'UTF-8'));
    }

    public function xlsx(bool $multiSheet = false, string $name = 'price-list.xlsx'): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Основной');
        $sheet->fromArray([
            ['Прайс поставщика'],
            ['Наименование', 'Цена', 'Валюта'],
            ['Мука', 1250.50, 'RUB'],
            ['Сахар', 980, 'RUB'],
        ]);
        $sheet->mergeCells('A1:C1');

        if ($multiSheet) {
            $second = $book->createSheet();
            $second->setTitle('Регион');
            $second->fromArray([['Наименование', 'Цена'], ['Соль', 42]]);
        }

        $path = $this->path($name);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function xlsWithFormula(string $name = 'price-list-formula.xls'): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['Наименование', 'Цена'], ['Крахмал', 100], ['Итого', '=SUM(B2:B2)']]);
        $sheet->mergeCells('A1:A1');
        $path = $this->path($name);
        (new Xls($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function docx(string $name = 'price-list.docx'): string
    {
        $word = new PhpWord;
        /** @var Section $section */
        $section = $word->addSection();
        $section->addText('Прайс-лист поставщика');
        $table = $section->addTable();
        foreach ([['Наименование', 'Цена'], ['Молоко сухое', '310,50'], ['Сливки', '420,00']] as $row) {
            $table->addRow();
            foreach ($row as $value) {
                $table->addCell()->addText($value);
            }
        }
        $path = $this->path($name);
        WordIOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    public function textPdf(string $name = 'price-list.pdf'): string
    {
        $stream = 'BT /F1 12 Tf 72 720 Td (Supplier wholesale price list August) Tj 0 -20 Td (Product name Price Currency VAT Package) Tj 0 -20 Td (Wheat flour premium 1250.50 RUB VAT included) Tj ET';

        return $this->pdf($name, [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ]);
    }

    public function scannedPdf(string $name = 'scanned-price-list.pdf'): string
    {
        $draw = 'q 400 0 0 300 72 420 cm /Im1 Do Q';
        $pixel = "\xff";

        return $this->pdf($name, [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($draw)." >>\nstream\n{$draw}\nendstream",
            '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>'."\nstream\n{$pixel}\nendstream",
        ]);
    }

    public function mixedPdf(string $name = 'mixed-price-list.pdf'): string
    {
        $text = 'BT /F1 12 Tf 72 720 Td (Supplier price list with text first page) Tj 0 -20 Td (Flour 1250.50 RUB) Tj ET';
        $draw = 'q 400 0 0 300 72 420 cm /Im1 Do Q';
        $pixel = "\x7f";

        return $this->pdf($name, [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Im1 8 0 R >> >> /Contents 6 0 R >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Length '.strlen($draw)." >>\nstream\n{$draw}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length 1 >>'."\nstream\n{$pixel}\nendstream",
        ]);
    }

    /** @param list<string> $objects */
    private function pdf(string $name, array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $this->write($name, $pdf);
    }

    public function png(string $name = 'phone-price.png'): string
    {
        return $this->write($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
    }

    public function corrupt(string $name = 'broken.xlsx'): string
    {
        return $this->write($name, 'not an office archive');
    }

    public function passwordProtectedPdf(string $name = 'protected.pdf'): string
    {
        return $this->write($name, "%PDF-1.4\n1 0 obj << /Encrypt 2 0 R >> endobj\n%%EOF\n");
    }

    public function empty(string $name = 'empty.csv'): string
    {
        return $this->write($name, '');
    }

    private function path(string $name): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->path($name);
        file_put_contents($path, $contents);

        return $path;
    }
}
