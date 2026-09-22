<?php

namespace Tests\Unit;

use App\Services\Mail\WordPreview\LegacyWordTextReader;
use App\Services\Mail\WordPreviewException;
use PHPUnit\Framework\TestCase;

class LegacyWordTextReaderTest extends TestCase
{
    public function test_it_reads_unicode_and_compressed_pieces_in_character_order(): void
    {
        [$document, $table] = $this->streams();

        $this->assertSame('Invoice: Счёт № 42', (new LegacyWordTextReader)->readStreams($document, $table));
    }

    public function test_it_skips_prc_records_and_limits_reading_to_the_main_story(): void
    {
        [$document, $table] = $this->streams();
        $table = "\x01".pack('v', 3).'abc'.$table;
        $document = substr_replace($document, pack('V', strlen($table)), 422, 4);
        $document = substr_replace($document, pack('V', 8), 76, 4);

        $this->assertSame('Invoice:', (new LegacyWordTextReader)->readStreams($document, $table));
    }

    public function test_it_rejects_encryption_and_xor_obfuscation(): void
    {
        foreach ([0x0100, 0x8100] as $flags) {
            [$document, $table] = $this->streams();
            $document = substr_replace($document, pack('v', $flags), 10, 2);

            try {
                (new LegacyWordTextReader)->readStreams($document, $table);
                $this->fail('Encrypted Word document was read.');
            } catch (WordPreviewException $exception) {
                $this->assertStringContainsString('защищён паролем', $exception->getMessage());
            }
        }
    }

    public function test_it_rejects_corrupt_offsets_lengths_piece_order_and_incomplete_story(): void
    {
        [$document, $table] = $this->streams();
        $cases = [
            [substr($document, 0, 100), $table],
            [substr_replace($document, pack('V', 0x7FFFFFFF), 418, 4), $table],
            [substr_replace($document, pack('V', 1000), 422, 4), $table],
            [$document, substr_replace($table, pack('V', 27), 1, 4)],
            [$document, substr_replace($table, pack('V', 1), 5, 4)],
            [$document, substr_replace($table, pack('V', 7), 13, 4)],
            [$document, substr_replace($table, pack('V', 0x3FFFFFFF), 19, 4)],
            [substr_replace($document, pack('V', 2000), 76, 4), $table],
        ];

        foreach ($cases as [$badDocument, $badTable]) {
            try {
                (new LegacyWordTextReader)->readStreams($badDocument, $badTable);
                $this->fail('Malformed piece data was accepted.');
            } catch (WordPreviewException $exception) {
                $this->assertStringContainsString('повреждён', $exception->getMessage());
            }
        }
    }

    /** A minimal Word97 FIB plus two actual text pieces in distinct encodings. */
    private function streams(): array
    {
        $ascii = 'Invoice: ';
        $unicode = mb_convert_encoding('Счёт № 42', 'UTF-16LE', 'UTF-8');
        $firstEnd = strlen($ascii);
        $lastEnd = $firstEnd + intdiv(strlen($unicode), 2);
        $document = str_repeat("\0", 1024);

        foreach ([0 => pack('v', 0xA5EC), 2 => pack('v', 0x00C1), 32 => pack('v', 14), 62 => pack('v', 22), 76 => pack('V', $lastEnd), 152 => pack('v', 93), 512 => $ascii, 700 => $unicode] as $offset => $value) {
            $document = substr_replace($document, $value, $offset, strlen($value));
        }

        $pieces = pack('VVV', 0, $firstEnd, $lastEnd)
            .pack('vVv', 0, 0x40000000 | (512 * 2), 0)
            .pack('vVv', 0, 700, 0);
        $table = "\x02".pack('V', strlen($pieces)).$pieces;
        $document = substr_replace($document, pack('VV', 0, strlen($table)), 418, 8);

        return [$document, $table];
    }
}
