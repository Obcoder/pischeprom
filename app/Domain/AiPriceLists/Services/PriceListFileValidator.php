<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\DTO\FileValidationResult;
use finfo;
use ZipArchive;

class PriceListFileValidator
{
    private const SUPPORTED_EXTENSIONS = [
        'xlsx', 'xls', 'csv', 'tsv', 'docx', 'pdf',
        'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'gif', 'heic'];

    private const DANGEROUS_MIMES = [
        'application/x-dosexec',
        'application/x-executable',
        'application/x-php',
        'application/x-sharedlib',
        'text/x-php',
    ];

    public function __construct(
        private readonly StoredFileMaterializer $materializer,
        private readonly FileScannerInterface $scanner,
    ) {}

    public function validate(string $disk, string $path, string $originalName): FileValidationResult
    {
        return $this->materializer->using($disk, $path, function (string $localPath) use ($originalName): FileValidationResult {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $size = (int) filesize($localPath);
            $sha256 = hash_file('sha256', $localPath);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($localPath) ?: 'application/octet-stream';

            if ($extension === 'doc') {
                return $this->error($extension, $mime, $size, $sha256, 'unsupported_doc', 'Формат DOC не поддерживается. Пересохраните файл в DOCX или PDF.', unsupported: true);
            }

            if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                return $this->error($extension, $mime, $size, $sha256, 'unsupported_format', 'Формат файла не поддерживается.', unsupported: true);
            }

            if ($size <= 0) {
                return $this->error($extension, $mime, $size, $sha256, 'empty_file', 'Файл пуст.');
            }

            if ($size > (int) config('ai-price-lists.limits.max_file_bytes')) {
                return $this->error($extension, $mime, $size, $sha256, 'file_too_large', 'Файл превышает допустимый размер.', unsupported: true);
            }

            if (in_array($mime, self::DANGEROUS_MIMES, true)) {
                return $this->error($extension, $mime, $size, $sha256, 'dangerous_mime', 'Тип содержимого небезопасен.', quarantined: true);
            }

            if ($this->isPasswordProtected($extension, $localPath)) {
                return $this->error(
                    $extension,
                    $mime,
                    $size,
                    $sha256,
                    'password_protected',
                    'Документ защищён паролем. Сохраните незашифрованную копию и отправьте её повторно.',
                    unsupported: true,
                );
            }

            if (! $this->mimeMatches($extension, $mime, $localPath)) {
                return $this->error($extension, $mime, $size, $sha256, 'mime_mismatch', 'Расширение файла не соответствует его содержимому.', quarantined: true);
            }

            if (in_array($extension, ['xlsx', 'docx'], true)) {
                $archiveError = $this->validateArchive($localPath, $extension);

                if ($archiveError !== null) {
                    return $this->error($extension, $mime, $size, $sha256, 'unsafe_archive', $archiveError, quarantined: true);
                }
            }

            $scan = $this->scanner->scan($localPath);

            if (! $scan->clean) {
                return $this->error($extension, $mime, $size, $sha256, 'malware_detected', $scan->reason ?: 'Файл не прошёл антивирусную проверку.', quarantined: true);
            }

            return new FileValidationResult(
                valid: true,
                quarantined: false,
                unsupported: false,
                extension: $extension,
                mimeType: $mime,
                sizeBytes: $size,
                sha256: $sha256,
                requiresOcr: in_array($extension, self::IMAGE_EXTENSIONS, true),
            );
        });
    }

    public function safeDisplayName(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '-', trim($name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?: 'price-list';

        return mb_substr($name, 0, 240);
    }

    private function mimeMatches(string $extension, string $mime, string $localPath): bool
    {
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return $this->imageMatches($extension, $mime, $localPath);
        }

        if ($extension === 'pdf') {
            return $mime === 'application/pdf' && str_starts_with((string) file_get_contents($localPath, false, null, 0, 5), '%PDF-');
        }

        if (in_array($extension, ['csv', 'tsv'], true)) {
            return (str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'application/octet-stream'], true))
                && $this->looksLikeText($localPath);
        }

        if ($extension === 'xls') {
            return str_starts_with((string) file_get_contents($localPath, false, null, 0, 8), "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")
                && in_array($mime, [
                    'application/vnd.ms-excel',
                    'application/x-cdf',
                    'application/x-ole-storage',
                    'application/octet-stream',
                ], true);
        }

        return in_array($mime, [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true);
    }

    private function imageMatches(string $extension, string $mime, string $localPath): bool
    {
        $header = (string) file_get_contents($localPath, false, null, 0, 16);

        return match ($extension) {
            'jpg', 'jpeg' => $mime === 'image/jpeg' && str_starts_with($header, "\xFF\xD8\xFF"),
            'png' => $mime === 'image/png' && str_starts_with($header, "\x89PNG\r\n\x1A\n"),
            'tif', 'tiff' => $mime === 'image/tiff' && (str_starts_with($header, "II*\0") || str_starts_with($header, "MM\0*")),
            'bmp' => in_array($mime, ['image/bmp', 'image/x-ms-bmp'], true) && str_starts_with($header, 'BM'),
            'gif' => $mime === 'image/gif' && (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')),
            'heic' => in_array($mime, ['image/heic', 'image/heif', 'application/octet-stream'], true)
                && str_starts_with(substr($header, 4), 'ftyp')
                && in_array(substr($header, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true),
            default => false,
        };
    }

    private function looksLikeText(string $localPath): bool
    {
        $sample = (string) file_get_contents($localPath, false, null, 0, 65536);

        if (str_contains($sample, "\0")) {
            return false;
        }

        $length = strlen($sample);

        if ($length === 0) {
            return true;
        }

        $controls = preg_match_all('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample);

        return $controls !== false && $controls / $length < 0.01;
    }

    private function isPasswordProtected(string $extension, string $localPath): bool
    {
        $header = (string) file_get_contents($localPath, false, null, 0, 16);

        if (in_array($extension, ['xlsx', 'docx'], true) && str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return true;
        }

        if ($extension !== 'pdf') {
            return false;
        }

        $contents = (string) file_get_contents($localPath);

        return preg_match('/\/Encrypt\b/', $contents) === 1;
    }

    private function validateArchive(string $localPath, string $extension): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($localPath, ZipArchive::RDONLY) !== true) {
            return 'Документ повреждён или защищён паролем.';
        }

        try {
            $entryLimit = (int) config('ai-price-lists.limits.max_zip_entries');
            $sizeLimit = (int) config('ai-price-lists.limits.max_uncompressed_bytes');
            $ratioLimit = (int) config('ai-price-lists.limits.max_compression_ratio');

            if ($zip->numFiles > $entryLimit) {
                return 'В документе слишком много архивных элементов.';
            }

            $uncompressed = 0;
            $compressed = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');

                if ($name === '' || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)~', $name)) {
                    return 'Архив содержит небезопасный путь.';
                }

                $uncompressed += (int) ($stat['size'] ?? 0);
                $compressed += max(1, (int) ($stat['comp_size'] ?? 0));

                if ($uncompressed > $sizeLimit || $uncompressed / max(1, $compressed) > $ratioLimit) {
                    return 'Архив превышает безопасный размер распаковки.';
                }
            }

            $requiredEntry = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';

            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($requiredEntry) === false) {
                return 'Структура офисного документа не соответствует расширению.';
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    private function error(
        string $extension,
        string $mime,
        int $size,
        string $sha256,
        string $code,
        string $message,
        bool $quarantined = false,
        bool $unsupported = false,
    ): FileValidationResult {
        return new FileValidationResult(
            valid: false,
            quarantined: $quarantined,
            unsupported: $unsupported,
            extension: $extension,
            mimeType: $mime,
            sizeBytes: $size,
            sha256: $sha256,
            requiresOcr: false,
            errorCode: $code,
            errorMessage: $message,
        );
    }
}
