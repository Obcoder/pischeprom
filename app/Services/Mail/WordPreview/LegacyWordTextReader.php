<?php

namespace App\Services\Mail\WordPreview;

use App\Services\Mail\WordPreviewException;
use PhpOffice\PhpWord\Shared\OLERead;

/** Reads only the main text story; never evaluates fields, macros or embedded objects. */
class LegacyWordTextReader
{
    public function read(string $path): string
    {
        $header = file_get_contents($path, false, null, 0, 512);

        // PHPWord's OLE reader supports the 512-byte sectors used by Word 97–2003.
        if (! is_string($header) || strlen($header) < 512 || $this->u16($header, 30) !== 9) {
            throw new WordPreviewException('Не удалось прочитать структуру DOC. Сохраните документ в DOCX или PDF.');
        }

        $ole = new OLERead;
        $ole->read($path);
        $document = $ole->getStream($ole->wrkdocument);

        if (! is_string($document) || strlen($document) < 32) {
            throw new WordPreviewException('Вложение не является документом Word.');
        }

        $tableName = ($this->u16($document, 10) & 0x0200) ? '1Table' : '0Table';

        foreach ($ole->props as $index => $entry) {
            if (($entry['name'] ?? null) === $tableName) {
                return $this->readStreams($document, (string) $ole->getStream($index));
            }
        }

        throw new WordPreviewException('В DOC отсутствует таблица текста. Возможно, файл повреждён.');
    }

    /**
     * MS-DOC 2.4.1: FIB → CLX → PlcPcd → UTF-16LE / compressed text pieces.
     * https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/01d5d8c4-cf9c-4ef9-80fd-439e763cfe01
     */
    public function readStreams(string $document, string $table): string
    {
        if ($this->u16($document, 0) !== 0xA5EC || $this->u16($document, 2) < 0x00C1) {
            throw new WordPreviewException('Этот формат DOC устарел. Сохраните документ в DOCX или PDF.');
        }

        // FibBase.fEncrypted includes both encryption and XOR obfuscation.
        if ($this->u16($document, 10) & 0x0100) {
            throw new WordPreviewException('Документ защищён паролем. Для просмотра нужна незашифрованная копия.');
        }

        $offset = 32;
        $offset += 2 + $this->u16($document, $offset) * 2;
        $longCount = $this->u16($document, $offset);

        if ($longCount < 4 || $longCount > 256) {
            $this->invalid();
        }

        $mainCharacters = $this->u32($document, $offset + 14);
        $offset += 2 + $longCount * 4;
        $pairCount = $this->u16($document, $offset);

        if ($pairCount < 34 || $pairCount > 512) {
            $this->invalid();
        }

        // fcClx/lcbClx are the 34th pair in FibRgFcLcb97.
        $clxOffset = $this->u32($document, $offset + 2 + 33 * 8);
        $clxLength = $this->u32($document, $offset + 6 + 33 * 8);
        $clx = $this->slice($table, $clxOffset, $clxLength);
        $position = 0;

        // CLX has zero or more Prc records (0x01), then one Pcdt (0x02).
        while ($position < strlen($clx) && ord($clx[$position]) === 1) {
            $position += 3 + $this->u16($clx, $position + 1);
        }

        if ($position >= strlen($clx) || ord($clx[$position]) !== 2) {
            $this->invalid();
        }

        $pieceLength = $this->u32($clx, $position + 1);

        if ($pieceLength < 4 || ($pieceLength - 4) % 12 !== 0 || $pieceLength > 240_004) {
            $this->invalid();
        }

        $pieces = $this->slice($clx, $position + 5, $pieceLength);
        $count = intdiv($pieceLength - 4, 12);
        $text = '';
        $previousEnd = 0;

        for ($index = 0; $index < $count; $index++) {
            $start = $this->u32($pieces, $index * 4);
            $end = $this->u32($pieces, ($index + 1) * 4);

            if ($start !== $previousEnd || $end < $start) {
                $this->invalid();
            }

            $previousEnd = $end;

            if ($start >= $mainCharacters) {
                break;
            }

            $characters = min($end, $mainCharacters) - $start;
            $fileOffset = $this->u32($pieces, 4 * ($count + 1) + 8 * $index + 2);
            $compressed = (bool) ($fileOffset & 0x40000000);

            if ($fileOffset & 0x80000000) {
                $this->invalid();
            }

            $fileOffset &= 0x3FFFFFFF;
            $fileOffset = $compressed ? intdiv($fileOffset, 2) : $fileOffset;
            $byteCount = $characters * ($compressed ? 1 : 2);
            $raw = $this->slice($document, $fileOffset, $byteCount);
            $text .= mb_convert_encoding($raw, 'UTF-8', $compressed ? 'Windows-1252' : 'UTF-16LE');

            // The renderer will mark this preview as truncated.
            if (mb_strlen($text) > WordDocumentExtractor::MAX_CHARACTERS) {
                return mb_substr($text, 0, WordDocumentExtractor::MAX_CHARACTERS + 1);
            }
        }

        if ($previousEnd < $mainCharacters) {
            $this->invalid();
        }

        // Preserve field results while discarding their non-displayed instructions.
        $text = preg_replace('/\x13[^\x13\x14\x15]*(?:\x14|\x15)/u', '', $text) ?? $text;

        return str_replace(["\x13", "\x14", "\x15", "\x07", "\x0b", "\x0c"], ['', '', '', "\t", "\n", "\n"], $text);
    }

    private function u16(string $data, int $offset): int
    {
        return unpack('v', $this->slice($data, $offset, 2))[1];
    }

    private function u32(string $data, int $offset): int
    {
        return unpack('V', $this->slice($data, $offset, 4))[1];
    }

    private function slice(string $data, int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($data) || $length > strlen($data) - $offset) {
            $this->invalid();
        }

        return substr($data, $offset, $length);
    }

    private function invalid(): never
    {
        throw new WordPreviewException('Не удалось прочитать текст DOC. Возможно, файл повреждён.');
    }
}
