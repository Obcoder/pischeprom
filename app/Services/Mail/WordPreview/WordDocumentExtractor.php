<?php

namespace App\Services\Mail\WordPreview;

use App\Services\Mail\WordPreviewException;
use DOMDocument;
use DOMElement;
use DOMNode;
use ZipArchive;

/** Produces text and table cells only. Office relationships and active content are never loaded. */
class WordDocumentExtractor
{
    public const MAX_CHARACTERS = 100_000;

    private int $characters = 0;

    private bool $truncated = false;

    public function extract(string $path, string $format): array
    {
        $this->characters = 0;
        $this->truncated = false;

        if ($format === 'doc') {
            $text = $this->boundedText((new LegacyWordTextReader)->read($path));
            $blocks = $text === '' ? [] : [['type' => 'paragraph', 'text' => $text]];
        } else {
            $blocks = $this->docx($path);
        }

        return ['blocks' => $blocks, 'truncated' => $this->truncated];
    }

    private function docx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new WordPreviewException('Не удалось открыть DOCX. Возможно, файл повреждён или защищён паролем.');
        }

        try {
            $this->validateArchive($zip);
            $xml = $zip->getFromName('word/document.xml', 8 * 1024 * 1024 + 1);

            if (! is_string($xml) || strlen($xml) > 8 * 1024 * 1024 || preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $xml)) {
                throw new WordPreviewException('DOCX содержит недопустимую XML-структуру или слишком большой текст.');
            }

            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);

            try {
                $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            if (! $loaded || $document->doctype !== null) {
                throw new WordPreviewException('Не удалось прочитать текст DOCX. Возможно, файл повреждён.');
            }

            $namespace = $document->documentElement?->namespaceURI;

            if (! in_array($namespace, ['http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'http://purl.oclc.org/ooxml/wordprocessingml/main'], true)) {
                throw new WordPreviewException('Структура вложения не соответствует документу DOCX.');
            }

            $body = $document->getElementsByTagNameNS($namespace, 'body')->item(0);

            if (! $body) {
                throw new WordPreviewException('В DOCX отсутствует содержимое документа.');
            }

            $blocks = [];

            foreach ($body->childNodes as $node) {
                if ($this->truncated || count($blocks) >= 1500) {
                    $this->truncated = true;
                    break;
                }

                if (! $node instanceof DOMElement) {
                    continue;
                }

                if ($node->localName === 'tbl') {
                    $rows = [];
                    foreach ($node->childNodes as $row) {
                        if (! $row instanceof DOMElement || $row->localName !== 'tr') {
                            continue;
                        }

                        if (count($rows) >= 500 || $this->truncated) {
                            $this->truncated = true;
                            break;
                        }

                        $cells = [];
                        foreach ($row->childNodes as $cell) {
                            if ($cell instanceof DOMElement && $cell->localName === 'tc') {
                                if (count($cells) >= 40 || $this->truncated) {
                                    $this->truncated = true;
                                    break;
                                }
                                $cells[] = $this->boundedText($this->nodeText($cell));
                            }
                        }
                        if ($cells !== []) {
                            $rows[] = $cells;
                        }
                    }
                    if ($rows !== []) {
                        $blocks[] = ['type' => 'table', 'rows' => $rows];
                    }
                } else {
                    $text = $this->boundedText($this->nodeText($node));
                    if ($text !== '') {
                        $blocks[] = ['type' => 'paragraph', 'text' => $text];
                    }
                }
            }

            return $blocks;
        } finally {
            $zip->close();
        }
    }

    private function validateArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles < 1 || $zip->numFiles > 512) {
            throw new WordPreviewException('В DOCX слишком много элементов для предпросмотра.');
        }

        $total = 0;
        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $name = (string) ($entry['name'] ?? '');
            $size = (int) ($entry['size'] ?? 0);
            $compressed = (int) ($entry['comp_size'] ?? 0);
            $total += $size;

            if ($name === '' || str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)|^[a-z]:~i', $name) || isset($names[$name])) {
                throw new WordPreviewException('DOCX содержит недопустимую структуру архива.');
            }

            if ($total > 40 * 1024 * 1024 || $size > 16 * 1024 * 1024 || $size / max(1, $compressed) > 200) {
                throw new WordPreviewException('DOCX превышает безопасный размер распаковки.');
            }

            if (($entry['encryption_method'] ?? 0) !== 0) {
                throw new WordPreviewException('Документ защищён паролем. Для просмотра нужна незашифрованная копия.');
            }

            $names[$name] = true;
        }

        if (! isset($names['[Content_Types].xml'], $names['word/document.xml'])) {
            throw new WordPreviewException('Структура вложения не соответствует документу DOCX.');
        }
    }

    private function nodeText(DOMNode $node, int $depth = 0): string
    {
        if ($depth > 40) {
            throw new WordPreviewException('В DOCX слишком сложная структура для предпросмотра.');
        }

        if ($node instanceof DOMElement) {
            if ($node->localName === 't') {
                return $node->textContent;
            }
            if (in_array($node->localName, ['br', 'cr'], true)) {
                return "\n";
            }
            if ($node->localName === 'tab') {
                return "\t";
            }
            if (in_array($node->localName, ['instrText', 'del', 'drawing', 'object', 'pict'], true)) {
                return '';
            }
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->nodeText($child, $depth + 1);
        }

        return $text.($node instanceof DOMElement && in_array($node->localName, ['p', 'tr'], true) ? "\n" : '');
    }

    private function boundedText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], ["\n", "\n"], mb_convert_encoding($text, 'UTF-8', 'UTF-8'));
        $text = trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $text) ?? '');
        $remaining = max(0, self::MAX_CHARACTERS - $this->characters);

        if (mb_strlen($text) > $remaining) {
            $text = mb_substr($text, 0, $remaining);
            $this->truncated = true;
        }

        $this->characters += mb_strlen($text);

        return $text;
    }
}
