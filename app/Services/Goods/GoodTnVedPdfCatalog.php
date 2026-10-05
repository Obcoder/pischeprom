<?php

namespace App\Services\Goods;

use RuntimeException;
use Smalot\PdfParser\Parser;

class GoodTnVedPdfCatalog
{
    public function __construct(private readonly Parser $parser) {}

    /** Only positive, exact table-row matches are evidence; PDF absence is inconclusive. */
    public function title(string $bytes, string $code): ?string
    {
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('Invalid PDF signature');
        }

        $pages = $this->parser->parseContent($bytes)->getPages();
        if (count($pages) > 160) {
            throw new RuntimeException('PDF page limit exceeded');
        }

        $formatted = substr($code, 0, 4).' '.substr($code, 4, 2).' '.substr($code, 6, 3).' '.substr($code, 9, 1);
        $codePattern = implode('\\h+', explode(' ', $formatted));
        foreach ($pages as $page) {
            $text = $page->getText();
            // Exclude legal notes, where references to a code do not establish a table entry.
            if (! preg_match('/Код\s+ТН\s+ВЭД\s+Наименование\s+позиции/u', $text, $header, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $table = substr($text, $header[0][1]);
            if (! preg_match('/^\h*'.$codePattern.'\h+[–−-]+\h*([^\r\n]+)/mu', $table, $match)) {
                continue;
            }

            // Preserve the official leaf wording, without adjacent tariff/unit columns.
            $title = preg_replace('/^(?:[–−-]\h*)+/u', '', $match[1]) ?? '';
            $title = preg_split('/\t|\h+[–−-]\h+(?=[0-9])|\h{3,}/u', $title, 2)[0];
            $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
            if ($title !== '' && mb_strlen($title) <= 1000) {
                return $title;
            }
        }

        return null;
    }
}
