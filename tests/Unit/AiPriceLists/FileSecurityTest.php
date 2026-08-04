<?php

namespace Tests\Unit\AiPriceLists;

use App\Domain\AiPriceLists\Exceptions\ExternalAiException;
use App\Domain\AiPriceLists\Exceptions\SafeRemoteDownloadException;
use App\Domain\AiPriceLists\Services\OcrInputPreparer;
use App\Domain\AiPriceLists\Services\PriceListFileValidator;
use App\Domain\AiPriceLists\Services\SafeRemoteFileDownloader;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FileSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set([
            'ai-price-lists.limits.max_file_bytes' => 1024,
            'ai-price-lists.scanner' => 'null',
        ]);
    }

    public function test_safe_name_removes_path_traversal_controls_and_nul_bytes(): void
    {
        $name = app(PriceListFileValidator::class)->safeDisplayName("../folder\\evil\0\nprice.csv");

        $this->assertSame('..-folder-evil-price.csv', $name);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('\\', $name);
    }

    public function test_mime_spoof_empty_unsupported_and_size_limits_are_classified(): void
    {
        Storage::disk('local')->put('spoof.pdf', '<?php echo "bad";');
        Storage::disk('local')->put('empty.csv', '');
        Storage::disk('local')->put('old.doc', 'legacy');
        Storage::disk('local')->put('large.csv', str_repeat('x', 1025));
        Storage::disk('local')->put('protected.pdf', "%PDF-1.4\n1 0 obj << /Encrypt 2 0 R >> endobj\n%%EOF\n");
        $validator = app(PriceListFileValidator::class);

        $this->assertTrue($validator->validate('local', 'spoof.pdf', 'spoof.pdf')->quarantined);
        $this->assertSame('empty_file', $validator->validate('local', 'empty.csv', 'empty.csv')->errorCode);
        $this->assertSame('unsupported_doc', $validator->validate('local', 'old.doc', 'old.doc')->errorCode);
        $this->assertSame('file_too_large', $validator->validate('local', 'large.csv', 'large.csv')->errorCode);
        $this->assertSame('password_protected', $validator->validate('local', 'protected.pdf', 'protected.pdf')->errorCode);
    }

    public function test_ssrf_guard_rejects_credentials_http_and_non_allowlisted_hosts(): void
    {
        config()->set('ai-price-lists.max.allowed_download_hosts', ['max.ru']);
        $downloader = app(SafeRemoteFileDownloader::class);

        foreach (['http://max.ru/file', 'https://user:pass@max.ru/file', 'https://max.ru:444/file', 'https://example.org/file'] as $url) {
            try {
                $downloader->assertSafeUrl($url);
                $this->fail("URL {$url} must be rejected.");
            } catch (SafeRemoteDownloadException $exception) {
                $this->assertStringStartsWith('max_', $exception->errorCode);
            }
        }
    }

    public function test_tiff_is_converted_to_supported_pdf_before_vision_ocr(): void
    {
        $finder = new ExecutableFinder;
        $ppm2tiff = $finder->find('ppm2tiff');
        $tiff2pdf = $finder->find('tiff2pdf');

        if (! $ppm2tiff || ! $tiff2pdf) {
            $this->markTestSkipped('libtiff command-line tools are not installed.');
        }

        $ppmPath = tempnam(sys_get_temp_dir(), 'price-list-ppm-');
        $tiffPath = tempnam(sys_get_temp_dir(), 'price-list-tiff-');

        try {
            file_put_contents($ppmPath, "P6\n2 2\n255\n".str_repeat("\xFF\xFF\xFF", 4));
            (new Process([$ppm2tiff, $ppmPath, $tiffPath]))->mustRun();
            config()->set([
                'ai-price-lists.limits.max_ocr_pages' => 5,
                'ai-price-lists.limits.max_image_pixels' => 1000,
                'ai-price-lists.limits.max_ocr_file_bytes' => 1024 * 1024,
                'ai-price-lists.ocr.tiff2pdf_binary' => $tiff2pdf,
            ]);

            $prepared = iterator_to_array(app(OcrInputPreparer::class)->requests($tiffPath, 'image/tiff', 'scan.tiff'));

            $this->assertCount(1, $prepared);
            $this->assertSame('application/pdf', $prepared[0]['request']->mimeType);
            $this->assertStringStartsWith('%PDF-', $prepared[0]['request']->content);
            $this->assertNull($prepared[0]['source_page']);
            $this->assertSame(1, $prepared[0]['expected_pages']);
        } finally {
            foreach ([$ppmPath, $tiffPath] as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_direct_image_is_rejected_before_ocr_when_pixel_limit_is_exceeded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'price-list-png-');

        try {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
            config()->set('ai-price-lists.limits.max_image_pixels', 0);

            iterator_to_array(app(OcrInputPreparer::class)->requests($path, 'image/png', 'photo.png'));
            $this->fail('Image over the configured pixel limit must be rejected.');
        } catch (ExternalAiException $exception) {
            $this->assertSame('ocr_image_pixel_limit', $exception->errorCode);
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
