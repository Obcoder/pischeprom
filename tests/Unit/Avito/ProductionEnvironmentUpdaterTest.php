<?php

namespace Tests\Unit\Avito;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionEnvironmentUpdaterTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_normalizes_legacy_url_and_bootstraps_webhook_secret_safely(): void
    {
        $envPath = $this->temporaryFile(implode("\n", [
            'APP_ENV=production',
            'AVITO_CLIENT_ID=preserved-client-id',
            'AVITO_CLIENT_SECRET=preserved-client-secret',
            'AVITO_API_URL=https://api.avito.ru/token',
            'AVITO_MUTATIONS_ENABLED=true',
            'AVITO_WEBHOOK_SECRET=',
            '',
        ]));

        $this->runUpdater($envPath);
        $contents = (string) file_get_contents($envPath);

        $this->assertStringContainsString("AVITO_CLIENT_ID=preserved-client-id\n", $contents);
        $this->assertStringContainsString("AVITO_CLIENT_SECRET=preserved-client-secret\n", $contents);
        $this->assertStringContainsString("AVITO_API_URL=https://api.avito.ru\n", $contents);
        $this->assertStringContainsString("AVITO_MUTATIONS_ENABLED=false\n", $contents);
        $this->assertMatchesRegularExpression('/^AVITO_WEBHOOK_SECRET=[A-Za-z0-9_-]{64}$/m', $contents);
        $this->assertSame(0600, fileperms($envPath) & 0777);
    }

    public function test_it_preserves_an_existing_valid_webhook_secret(): void
    {
        $secret = str_repeat('a', 64);
        $envPath = $this->temporaryFile("AVITO_WEBHOOK_SECRET={$secret}\n");

        $this->runUpdater($envPath);
        $this->runUpdater($envPath);
        $contents = (string) file_get_contents($envPath);

        $this->assertSame(1, substr_count($contents, "AVITO_WEBHOOK_SECRET={$secret}"));
    }

    private function runUpdater(string $envPath): void
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/update-production-avito-env.php'),
            $envPath,
        ]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-avito-env-test-');

        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        chmod($path, 0600);
        $this->temporaryPaths[] = $path;

        return $path;
    }
}
