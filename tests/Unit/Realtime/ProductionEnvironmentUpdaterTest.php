<?php

namespace Tests\Unit\Realtime;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProductionEnvironmentUpdaterTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_it_generates_server_credentials_without_output_and_is_idempotent(): void
    {
        $path = $this->environment("APP_URL=https://warehouse.example.test\nDB_PASSWORD=keep-secret\n");
        chmod($path, 0644);
        $firstRun = $this->runUpdater($path);
        $this->assertSame(0, $firstRun->getExitCode(), $firstRun->getErrorOutput());
        $this->assertSame('', $firstRun->getOutput());
        $contents = file_get_contents($path);
        $this->assertStringContainsString("DB_PASSWORD=keep-secret\n", $contents);
        $this->assertStringContainsString("REVERB_HOST=127.0.0.1\n", $contents);
        $this->assertStringContainsString("REVERB_PORT=8085\n", $contents);
        $this->assertStringContainsString("REVERB_ALLOWED_ORIGINS=warehouse.example.test\n", $contents);
        $this->assertStringContainsString("REALTIME_WS_HOST=warehouse.example.test\n", $contents);
        $this->assertStringContainsString("REALTIME_WS_PATH=/realtime\n", $contents);
        $this->assertStringContainsString("BROADCAST_CONNECTION=reverb\n", $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_SECRET=[a-f0-9]{64}$/m', $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_KEY=[a-f0-9]{48}$/m', $contents);
        $this->assertSame(0640, fileperms($path) & 0777);
        $this->assertSame(0, $this->runUpdater($path)->getExitCode());
        $this->assertSame($contents, file_get_contents($path));
    }

    public function test_it_preserves_credentials_and_removes_duplicate_or_exported_legacy_settings(): void
    {
        $secret = str_repeat('safe-secret-', 4);
        $path = $this->environment(implode("\n", [
            'APP_URL="https://warehouse.example.test/"',
            'REVERB_APP_ID=123456',
            'REVERB_APP_KEY=existing-key',
            "REVERB_APP_SECRET='{$secret}'",
            'REVERB_HOST=public.example.test',
            ' export REVERB_HOST = "other.example.test"',
            'REALTIME_WS_PORT=8080',
            'REVERB_ALLOWED_ORIGINS=*',
            '',
        ]));
        $process = $this->runUpdater($path);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $contents = file_get_contents($path);
        $this->assertStringContainsString("REVERB_APP_SECRET={$secret}\n", $contents);
        $this->assertStringContainsString("REVERB_APP_ID=123456\n", $contents);
        $this->assertStringContainsString("REVERB_APP_KEY=existing-key\n", $contents);
        $this->assertSame(1, substr_count($contents, 'REVERB_HOST='));
        $this->assertStringNotContainsString('other.example.test', $contents);
        $this->assertStringContainsString("REALTIME_WS_PORT=443\n", $contents);
    }

    public function test_it_refuses_an_invalid_production_url_without_changing_the_environment(): void
    {
        foreach (['http://example.test', 'https://example.test:8443', 'https://example.test/app', 'https://user:secret@example.test'] as $url) {
            $original = "APP_URL={$url}\nDB_PASSWORD=private-value\n";
            $path = $this->environment($original);
            $process = $this->runUpdater($path);
            $this->assertNotSame(0, $process->getExitCode());
            $this->assertStringNotContainsString('private-value', $process->getErrorOutput());
            $this->assertSame($original, file_get_contents($path));
        }
    }

    public function test_it_does_not_rotate_invalid_existing_credentials_or_leak_parser_input(): void
    {
        foreach ([
            "APP_URL=https://example.test\nREVERB_APP_SECRET=invalid.secret\n",
            "APP_URL=https://example.test\nDB_PASSWORD=\"unterminated-private-value\n",
        ] as $original) {
            $path = $this->environment($original);
            $process = $this->runUpdater($path);
            $this->assertNotSame(0, $process->getExitCode());
            $this->assertSame($original, file_get_contents($path));
            $this->assertStringNotContainsString('unterminated-private-value', $process->getErrorOutput());
            $this->assertStringNotContainsString('invalid.secret', $process->getErrorOutput());
        }
    }

    public function test_it_refuses_symlinks(): void
    {
        $path = $this->environment("APP_URL=https://example.test\n");
        $link = $path.'-link';
        $this->temporaryPaths[] = $link;
        symlink($path, $link);
        $this->assertNotSame(0, $this->runUpdater($link)->getExitCode());
        $this->assertSame("APP_URL=https://example.test\n", file_get_contents($path));
    }

    private function environment(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'realtime-env-test-');
        $this->assertNotFalse($path);
        $this->temporaryPaths[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    private function runUpdater(string $path): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 3).'/scripts/update-production-realtime-env.php', $path]);
        $process->run();

        return $process;
    }
}
