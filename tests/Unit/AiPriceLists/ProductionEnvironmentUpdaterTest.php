<?php

namespace Tests\Unit\AiPriceLists;

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

    public function test_it_bootstraps_max_credentials_without_exposing_auto_apply(): void
    {
        $envPath = $this->temporaryFile("APP_ENV=production\nMAX_WEBHOOK_SECRET=\n");
        $aiKeyPath = $this->temporaryFile('AQVN'.str_repeat('A', 40));
        $maxTokenPath = $this->temporaryFile('max-production-token.with_symbols');

        $this->runUpdater($envPath, $aiKeyPath, $maxTokenPath);

        $contents = (string) file_get_contents($envPath);

        $this->assertStringContainsString("MAX_API_URL=https://platform-api2.max.ru\n", $contents);
        $this->assertStringContainsString("MAX_ACCESS_TOKEN=max-production-token.with_symbols\n", $contents);
        $this->assertStringContainsString("AI_PRICE_LIST_AUTHORIZATION_ENABLED=false\n", $contents);
        $this->assertStringContainsString("AI_PRICE_LIST_MAIL_QUEUE=mail-sync\n", $contents);
        $this->assertStringContainsString("PRICE_LIST_AUTO_APPLY=false\n", $contents);
        $this->assertMatchesRegularExpression('/^MAX_WEBHOOK_SECRET=[A-Za-z0-9_-]{64}$/m', $contents);
    }

    public function test_it_preserves_an_existing_valid_max_webhook_secret(): void
    {
        $envPath = $this->temporaryFile("MAX_WEBHOOK_SECRET=existing_Webhook-Secret\n");
        $aiKeyPath = $this->temporaryFile('AQVN'.str_repeat('B', 40));
        $maxTokenPath = $this->temporaryFile('replacement-token');

        $this->runUpdater($envPath, $aiKeyPath, $maxTokenPath);

        $contents = (string) file_get_contents($envPath);

        $this->assertSame(1, substr_count($contents, 'MAX_WEBHOOK_SECRET=existing_Webhook-Secret'));
    }

    private function runUpdater(string $envPath, string $aiKeyPath, string $maxTokenPath): void
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/update-production-price-list-env.php'),
            $envPath,
            $aiKeyPath,
            'b1gk5nkr1v8fa8as88kt',
            '/run/clamav/clamd.ctl',
            $maxTokenPath,
        ]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-env-test-');

        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        $this->temporaryPaths[] = $path;

        return $path;
    }
}
