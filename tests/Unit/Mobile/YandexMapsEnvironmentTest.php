<?php

namespace Tests\Unit\Mobile;

use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class YandexMapsEnvironmentTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_activation_preserves_other_settings_is_idempotent_and_never_logs_credentials(): void
    {
        $preserved = "# Unrelated settings\r\nAPP_KEY='keep-secret'\r\nMOBILE_ALLOW_NEGATIVE_STOCK=true\r\n";
        $env = $this->file($preserved."export YANDEX_MAPS_API_KEY = '' # old\r\nYANDEX_MAP_SCRIPT_URL=https://api-maps.yandex.ru/2.1/\r\n");
        $payload = $this->file(json_encode(['api_key' => 'synthetic-yandex-secret-12345', 'script_url' => 'https://enterprise.api-maps.yandex.ru/2.1/']));
        $original = file_get_contents($env);
        $check = $this->runUpdater($env, $payload, true);
        $this->assertSame(0, $check->getExitCode());
        $this->assertSame($original, file_get_contents($env));
        $result = $this->runUpdater($env, $payload);
        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $saved = file_get_contents($env);
        $this->assertStringStartsWith($preserved, $saved);
        $this->assertSame('synthetic-yandex-secret-12345', Dotenv::parse($saved)['YANDEX_MAPS_API_KEY']);
        $this->assertSame('https://enterprise.api-maps.yandex.ru/2.1/', Dotenv::parse($saved)['YANDEX_MAP_SCRIPT_URL']);
        $this->assertStringNotContainsString('synthetic-yandex-secret', $result->getOutput().$result->getErrorOutput().$check->getOutput());
        $this->assertSame(0, $this->runUpdater($env, $payload)->getExitCode());
        $this->assertSame($saved, file_get_contents($env));
        clearstatcache(true, $env);
        $this->assertSame(0600, fileperms($env) & 0777);
    }

    public function test_optional_script_is_preserved_and_no_trailing_newline_is_supported(): void
    {
        $env = $this->file('YANDEX_MAP_SCRIPT_URL=https://enterprise.api-maps.yandex.ru/2.1/');
        $payload = $this->file(json_encode(['api_key' => 'synthetic-yandex-secret-12345']));
        $this->assertSame(0, $this->runUpdater($env, $payload)->getExitCode());
        $values = Dotenv::parse(file_get_contents($env));
        $this->assertSame('https://enterprise.api-maps.yandex.ru/2.1/', $values['YANDEX_MAP_SCRIPT_URL']);
        $this->assertSame('synthetic-yandex-secret-12345', $values['YANDEX_MAPS_API_KEY']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_cannot_mutate_environment_or_leak_secrets(string $payload): void
    {
        $env = $this->file("APP_KEY=keep-secret\nYANDEX_MAPS_API_KEY=old-key\n");
        $original = file_get_contents($env);
        $result = $this->runUpdater($env, $this->file($payload));
        $this->assertNotSame(0, $result->getExitCode());
        $this->assertSame($original, file_get_contents($env));
        $this->assertStringNotContainsString('synthetic-yandex-secret', $result->getErrorOutput().$result->getOutput());
    }

    public static function invalidPayloads(): array
    {
        return [
            'empty key' => ['{"api_key":""}'],
            'newline injection' => [json_encode(['api_key' => "synthetic-yandex-secret\nAPP_ENV=local"])],
            'external SDK' => [json_encode(['api_key' => 'synthetic-yandex-secret-12345', 'script_url' => 'https://untrusted.example/2.1/'])],
            'malformed JSON' => ['{"api_key":"synthetic-yandex-secret'],
        ];
    }

    public function test_invalid_dotenv_and_symlinks_are_rejected_before_changes(): void
    {
        $env = $this->file("APP_KEY='unterminated-secret\n");
        $original = file_get_contents($env);
        $payload = $this->file(json_encode(['api_key' => 'synthetic-yandex-secret-12345']));
        $result = $this->runUpdater($env, $payload);
        $this->assertNotSame(0, $result->getExitCode());
        $this->assertSame($original, file_get_contents($env));
        $this->assertStringNotContainsString('unterminated-secret', $result->getErrorOutput());
        $link = $this->file('');
        unlink($link);
        symlink($env, $link);
        $this->assertNotSame(0, $this->runUpdater($link, $payload)->getExitCode());
        $this->assertSame($original, file_get_contents($env));
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-maps-test-');
        file_put_contents($path, $contents);
        chmod($path, 0600);
        $this->files[] = $path;

        return $path;
    }

    private function runUpdater(string $env, string $payload, bool $check = false): Process
    {
        $process = new Process([PHP_BINARY, base_path('scripts/configure-production-yandex-maps.php'), $env, $payload, ...($check ? ['--check'] : [])]);
        $process->run();

        return $process;
    }
}
