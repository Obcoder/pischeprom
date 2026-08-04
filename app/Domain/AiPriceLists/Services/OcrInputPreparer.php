<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use Generator;
use Imagick;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

class OcrInputPreparer
{
    /**
     * @param  list<int>  $selectedPdfPages
     * @return Generator<int, array{request:OcrRequest, source_page:?int, expected_pages:int}>
     */
    public function requests(
        string $localPath,
        string $mimeType,
        string $fileName,
        array $selectedPdfPages = [],
        ?int $totalPdfPages = null,
    ): Generator {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            if ($selectedPdfPages === [] || ($totalPdfPages !== null && count($selectedPdfPages) >= $totalPdfPages)) {
                yield [
                    'request' => new OcrRequest((string) file_get_contents($localPath), $mimeType, $fileName),
                    'source_page' => null,
                    'expected_pages' => max(1, $totalPdfPages ?? 1),
                ];

                return;
            }

            foreach ($selectedPdfPages as $pageNumber) {
                yield $this->rasterizedPdfPage($localPath, $fileName, $pageNumber);
            }

            return;
        }

        if (in_array($extension, ['tif', 'tiff'], true)) {
            $this->assertImageDimensions($localPath);
            yield $this->convertedTiffPdf($localPath, $fileName);

            return;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && ! $this->needsOrientationCorrection($localPath, $extension)) {
            $this->assertImageDimensions($localPath);

            yield [
                'request' => new OcrRequest((string) file_get_contents($localPath), $mimeType, $fileName),
                'source_page' => null,
                'expected_pages' => 1,
            ];

            return;
        }

        yield from $this->convertedImageFrames($localPath, $fileName);
    }

    private function assertImageDimensions(string $localPath): void
    {
        $dimensions = @getimagesize($localPath);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $limit = (int) config('ai-price-lists.limits.max_image_pixels');

        if ($width <= 0 || $height <= 0) {
            throw new ExternalAiException('Изображение повреждено или не содержит допустимых размеров.', false, 'ocr_image_invalid');
        }

        if ($limit <= 0 || $width > intdiv($limit, $height)) {
            throw new ExternalAiException('Изображение превышает безопасный лимит пикселей.', false, 'ocr_image_pixel_limit');
        }
    }

    /** @return array{request:OcrRequest, source_page:null, expected_pages:int} */
    private function convertedTiffPdf(string $localPath, string $fileName): array
    {
        $binary = trim((string) config('ai-price-lists.ocr.tiff2pdf_binary'));

        if ($binary === '') {
            throw new ExternalAiException(
                'Для TIFF требуется настроенный безопасный конвертер tiff2pdf.',
                false,
                'ocr_tiff_converter_unavailable',
            );
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'price-list-tiff-pdf-');

        if ($outputPath === false) {
            throw new ExternalAiException('Не удалось подготовить временный TIFF-файл.', false, 'ocr_temporary_file_failed');
        }

        try {
            unlink($outputPath);
            $process = new Process([$binary, '-z', '-o', $outputPath, $localPath]);
            $process->setTimeout(max(30, (int) config('ai-price-lists.limits.timeout_seconds')));
            $process->setIdleTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new ExternalAiException(
                    'TIFF повреждён или серверный конвертер tiff2pdf недоступен.',
                    false,
                    'ocr_tiff_conversion_failed',
                );
            }

            $size = (int) filesize($outputPath);
            $content = (string) file_get_contents($outputPath);

            if ($size <= 0 || $size > (int) config('ai-price-lists.limits.max_ocr_file_bytes') || ! str_starts_with($content, '%PDF-')) {
                throw new ExternalAiException('Подготовленный TIFF превышает безопасный размер.', false, 'ocr_file_too_large');
            }

            try {
                $pages = count((new Parser)->parseFile($outputPath)->getPages());
            } catch (Throwable) {
                throw new ExternalAiException('Не удалось проверить страницы TIFF перед OCR.', false, 'ocr_tiff_conversion_failed');
            }

            if ($pages < 1 || $pages > (int) config('ai-price-lists.limits.max_ocr_pages')) {
                throw new ExternalAiException('TIFF превышает лимит OCR-страниц.', false, 'ocr_page_limit');
            }

            $base = pathinfo($fileName, PATHINFO_FILENAME) ?: 'price-list';

            return [
                'request' => new OcrRequest($content, 'application/pdf', "{$base}.pdf"),
                'source_page' => null,
                'expected_pages' => $pages,
            ];
        } catch (ExternalAiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ExternalAiException(
                'TIFF повреждён или серверный конвертер tiff2pdf недоступен.',
                false,
                'ocr_tiff_conversion_failed',
            );
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /** @return array{request:OcrRequest, source_page:int, expected_pages:int} */
    private function rasterizedPdfPage(string $localPath, string $fileName, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new ExternalAiException('Некорректный номер PDF-страницы для OCR.', false, 'ocr_page_invalid');
        }

        $this->requireImagick('Для OCR отдельных страниц смешанного PDF требуется PHP Imagick с PDF delegate.');
        $image = new Imagick;

        try {
            $this->configureResources();
            $image->setResolution(180, 180);
            $image->readImage($localPath.'['.($pageNumber - 1).']');
            $image->setIteratorIndex(0);

            return $this->pngRequest($image, $fileName, $pageNumber);
        } catch (ExternalAiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ExternalAiException(
                'Не удалось безопасно подготовить отдельную PDF-страницу для OCR. Проверьте Imagick/Ghostscript.',
                false,
                'ocr_pdf_rasterization_failed',
            );
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    /** @return Generator<int, array{request:OcrRequest, source_page:int, expected_pages:int}> */
    private function convertedImageFrames(string $localPath, string $fileName): Generator
    {
        $this->requireImagick('Для этого формата изображения требуется PHP Imagick.');
        $image = new Imagick;

        try {
            $this->configureResources();
            $image->readImage($localPath);
            $frameCount = $image->getNumberImages();
            $pageLimit = max(1, (int) config('ai-price-lists.limits.max_ocr_pages'));

            if ($frameCount > $pageLimit) {
                throw new ExternalAiException('Изображение превышает лимит OCR-страниц.', false, 'ocr_page_limit');
            }

            foreach ($image as $index => $frame) {
                yield $this->pngRequest($frame, $fileName, $index + 1);
            }
        } catch (ExternalAiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ExternalAiException(
                'Изображение повреждено или его формат не поддерживается серверным декодером.',
                false,
                'ocr_image_conversion_failed',
            );
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    /** @return array{request:OcrRequest, source_page:int, expected_pages:int} */
    private function pngRequest(Imagick $image, string $fileName, int $pageNumber): array
    {
        if (method_exists($image, 'autoOrientImage')) {
            $image->autoOrientImage();
        }

        $pixels = $image->getImageWidth() * $image->getImageHeight();

        if ($pixels <= 0 || $pixels > (int) config('ai-price-lists.limits.max_image_pixels')) {
            throw new ExternalAiException('Изображение превышает безопасный лимит пикселей.', false, 'ocr_image_pixel_limit');
        }

        $image->setImageFormat('png');
        $image->stripImage();
        $content = $image->getImageBlob();

        if ($content === '' || strlen($content) > (int) config('ai-price-lists.limits.max_ocr_file_bytes')) {
            throw new ExternalAiException('Подготовленная OCR-страница превышает безопасный размер.', false, 'ocr_file_too_large');
        }

        $base = pathinfo($fileName, PATHINFO_FILENAME) ?: 'price-list';

        return [
            'request' => new OcrRequest($content, 'image/png', "{$base}-page-{$pageNumber}.png"),
            'source_page' => $pageNumber,
            'expected_pages' => 1,
        ];
    }

    private function needsOrientationCorrection(string $localPath, string $extension): bool
    {
        if (! in_array($extension, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data') || ! class_exists(Imagick::class)) {
            return false;
        }

        try {
            $exif = @exif_read_data($localPath, 'IFD0', true, false);
            $orientation = (int) data_get($exif, 'IFD0.Orientation', data_get($exif, 'Orientation', 1));

            return $orientation > 1 && $orientation <= 8;
        } catch (Throwable) {
            return false;
        }
    }

    private function requireImagick(string $message): void
    {
        if (! class_exists(Imagick::class)) {
            throw new ExternalAiException($message, false, 'ocr_image_converter_unavailable');
        }
    }

    private function configureResources(): void
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, max(30, (int) config('ai-price-lists.limits.timeout_seconds')));
    }
}
