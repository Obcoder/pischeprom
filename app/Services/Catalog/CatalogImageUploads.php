<?php

namespace App\Services\Catalog;

use DOMDocument;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class CatalogImageUploads
{
    public function store(UploadedFile $file, string $directory, string $field = 'image'): string
    {
        if (! in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true)) {
            $path = $file->store($directory, 'public');
            if (! $path) {
                throw new RuntimeException('Не удалось сохранить аватар.');
            }

            return $path;
        }

        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $previousErrors = libxml_use_internal_errors(true);
        try {
            try {
                $svg = $sanitizer->sanitize($file->getContent());
            } catch (LogicException) {
                $svg = false;
            }
            $document = new DOMDocument;
            $valid = is_string($svg) && $svg !== '' && $document->loadXML($svg, LIBXML_NONET)
                && $document->documentElement?->localName === 'svg'
                && $document->documentElement?->namespaceURI === 'http://www.w3.org/2000/svg';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        if (! $valid) {
            throw ValidationException::withMessages([$field => 'Не удалось прочитать SVG. Выберите корректный SVG-файл.']);
        }

        $path = $directory.'/'.Str::uuid().'.svg';
        if (! Storage::disk('public')->put($path, $svg)) {
            throw new RuntimeException('Не удалось сохранить аватар.');
        }

        return $path;
    }
}
