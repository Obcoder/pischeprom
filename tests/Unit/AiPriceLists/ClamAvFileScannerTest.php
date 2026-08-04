<?php

namespace Tests\Unit\AiPriceLists;

use App\Domain\AiPriceLists\Services\ClamAvFileScanner;
use RuntimeException;
use Tests\TestCase;

class ClamAvFileScannerTest extends TestCase
{
    private string $binaryPath;

    private string $samplePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->binaryPath = tempnam(sys_get_temp_dir(), 'fake-clamdscan-');
        $this->samplePath = tempnam(sys_get_temp_dir(), 'clamav-sample-');
        file_put_contents($this->samplePath, "harmless\n");
        config()->set([
            'ai-price-lists.clamav_socket' => '/tmp/fake-clamd.sock',
            'ai-price-lists.clamdscan_binary' => $this->binaryPath,
            'ai-price-lists.clamd_config' => '',
            'ai-price-lists.clamdscan_timeout_seconds' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->binaryPath, $this->samplePath] as $path) {
            if (isset($path) && is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_clean_exit_code_is_accepted(): void
    {
        $this->fakeExitCode(0);

        $result = app(ClamAvFileScanner::class)->scan($this->samplePath);

        $this->assertTrue($result->clean);
        $this->assertSame('clamav', $result->scanner);
    }

    public function test_detection_exit_code_is_quarantined_without_provider_output(): void
    {
        $this->fakeExitCode(1);

        $result = app(ClamAvFileScanner::class)->scan($this->samplePath);

        $this->assertFalse($result->clean);
        $this->assertSame('clamav', $result->scanner);
        $this->assertSame('Обнаружено потенциально опасное содержимое.', $result->reason);
    }

    public function test_scanner_error_exit_code_fails_closed(): void
    {
        $this->fakeExitCode(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ClamAV returned an indeterminate result.');

        app(ClamAvFileScanner::class)->scan($this->samplePath);
    }

    private function fakeExitCode(int $exitCode): void
    {
        file_put_contents($this->binaryPath, "#!/bin/sh\nexit {$exitCode}\n");
        chmod($this->binaryPath, 0700);
    }
}
