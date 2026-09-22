<?php

namespace Tests\Unit;

use App\Services\Mail\WordAttachmentPreviewer;
use App\Services\Mail\WordPreviewException;
use Tests\TestCase;
use ZipArchive;

class WordAttachmentPreviewerTest extends TestCase
{
    public function test_it_reads_a_real_legacy_doc_with_cyrillic_without_office_or_external_services(): void
    {
        $before = glob(sys_get_temp_dir().'/mail-word-*');
        $result = (new WordAttachmentPreviewer)->preview(file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.doc')), 'Счёт поставщика.doc');

        $this->assertSame(trim(file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.txt'))), $result['text']);
        $this->assertSame('doc', $result['format']);
        $this->assertTrue($result['has_text']);
        $this->assertFalse($result['truncated']);
        $this->assertStringContainsString('Счёт на оплату № 42', $result['html']);
        $this->assertSame($before, glob(sys_get_temp_dir().'/mail-word-*'));
    }

    public function test_it_reads_the_zero_table_stream_selected_by_the_fib_flag(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.doc'));
        $content = str_replace(mb_convert_encoding('1Table', 'UTF-16LE'), mb_convert_encoding('0Table', 'UTF-16LE'), $content);
        // The synthetic fixture's WordDocument starts in sector zero, after its 512-byte header.
        $flags = unpack('v', substr($content, 522, 2))[1] & ~0x0200;
        $content = substr_replace($content, pack('v', $flags), 522, 2);

        $result = (new WordAttachmentPreviewer)->preview($content, 'zero-table.doc');
        $this->assertStringContainsString('Желатин пищевой — 25 кг.', $result['text']);
    }

    public function test_it_preserves_docx_paragraphs_and_tables_without_rendering_document_html_or_links(): void
    {
        $body = '<w:p><w:r><w:t>Счёт &lt;script&gt;alert(1)&lt;/script&gt;</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Товар</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Цена</w:t></w:r></w:p></w:tc></w:tr>'
            .'<w:tr><w:tc><w:p><w:r><w:t>Желатин</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>1250 ₽</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'<w:p><w:hyperlink r:id="external"><w:r><w:t>Название ссылки</w:t></w:r></w:hyperlink></w:p>'
            .'<w:p><w:r><w:instrText>INCLUDETEXT secret</w:instrText><w:t>Видимый результат</w:t></w:r></w:p>';
        $result = (new WordAttachmentPreviewer)->preview($this->docx($body, ['word/_rels/document.xml.rels' => '<Relationships><Relationship Id="external" TargetMode="External" Target="http://127.0.0.1/private"/></Relationships>']), 'offer.docx');

        $this->assertSame('docx', $result['format']);
        $this->assertStringContainsString("Товар\tЦена\nЖелатин\t1250 ₽", $result['text']);
        $this->assertStringContainsString('<table><tr><td>Товар</td><td>Цена</td></tr>', $result['html']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result['html']);
        $this->assertStringNotContainsString('<script>', $result['html']);
        $this->assertStringNotContainsString('href=', $result['html']);
        $this->assertStringNotContainsString('127.0.0.1', $result['html']);
        $this->assertStringNotContainsString('INCLUDETEXT', $result['text']);
        $this->assertStringContainsString('Content-Security-Policy', $result['html']);
    }

    public function test_it_rejects_xml_entities_zip_traversal_bombs_and_non_word_archives(): void
    {
        $badFiles = [
            $this->zip(['[Content_Types].xml' => '', 'word/document.xml' => '<!DOCTYPE x [<!ENTITY file SYSTEM "file:///etc/passwd">]><x>&file;</x>']),
            $this->docx('<w:p/>', ['../outside.xml' => 'bad']),
            $this->docx('<w:p/>', ['huge.bin' => str_repeat('A', 2 * 1024 * 1024)]),
            $this->zip(['test.xml' => '<test/>']),
            $this->docx('<w:p><broken>'),
        ];

        foreach ($badFiles as $content) {
            try {
                (new WordAttachmentPreviewer)->preview($content, 'bad.docx');
                $this->fail('Unsafe DOCX was read.');
            } catch (WordPreviewException $exception) {
                $this->assertSame(422, $exception->status);
                $this->assertStringNotContainsString('/etc/passwd', $exception->getMessage());
                $this->assertStringNotContainsString('root:', $exception->getMessage());
            }
        }
    }

    public function test_it_bounds_actual_file_bytes_and_rejects_mismatched_formats_and_malformed_ole(): void
    {
        $files = [
            ['', 'empty.doc'],
            ['hello', 'wrong.doc'],
            ['hello', 'wrong.docx'],
            ['hello', 'file.txt'],
            [str_repeat('x', WordAttachmentPreviewer::MAX_FILE_BYTES + 1), 'huge.doc'],
            ["\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1".str_repeat("\0", 1000), 'broken.doc'],
            ["\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1".str_repeat("\0", 1000), 'encrypted.docx'],
        ];

        foreach ($files as [$content, $name]) {
            try {
                (new WordAttachmentPreviewer)->preview($content, $name);
                $this->fail('Invalid file was read.');
            } catch (WordPreviewException $exception) {
                $this->assertSame(422, $exception->status);
            }
        }
    }

    public function test_an_empty_document_and_truncated_text_have_explicit_states(): void
    {
        $empty = (new WordAttachmentPreviewer)->preview($this->docx('<w:p/>'), 'empty.docx');
        $this->assertFalse($empty['has_text']);
        $this->assertSame('', $empty['text']);

        $paragraphs = '';
        for ($index = 0; $index < 1300; $index++) {
            $paragraphs .= '<w:p><w:r><w:t>'.hash('sha512', (string) $index).'</w:t></w:r></w:p>';
        }
        $large = (new WordAttachmentPreviewer)->preview($this->docx($paragraphs), 'large.docx');
        $this->assertTrue($large['truncated']);
        $this->assertLessThanOrEqual(102_000, mb_strlen($large['text']));
    }

    public function test_a_cyclic_ole_sector_chain_is_stopped_by_the_isolated_worker(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/Mail/word-preview-cyrillic.doc'));
        $fatSector = unpack('V', substr($content, 76, 4))[1];
        // WordDocument sector zero points back to itself instead of the next sector.
        $content = substr_replace($content, pack('V', 0), ($fatSector + 1) * 512, 4);
        $started = microtime(true);
        $before = glob(sys_get_temp_dir().'/mail-word-*');

        try {
            (new WordAttachmentPreviewer)->preview($content, 'cyclic.doc');
            $this->fail('A cyclic OLE chain was accepted.');
        } catch (WordPreviewException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertLessThan(15, microtime(true) - $started);
            $this->assertSame($before, glob(sys_get_temp_dir().'/mail-word-*'));
            $this->assertStringNotContainsString('memory', $exception->getMessage());
        }
    }

    private function docx(string $body, array $extra = []): string
    {
        return $this->zip([
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>',
            ...$extra,
        ]);
    }

    private function zip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'word-fixture-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        try {
            return file_get_contents($path);
        } finally {
            unlink($path);
        }
    }
}
