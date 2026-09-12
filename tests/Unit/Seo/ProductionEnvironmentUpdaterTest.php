<?php

namespace Tests\Unit\Seo;

use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

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

    public function test_activation_is_idempotent_and_preserves_credentials_and_unrelated_settings(): void
    {
        $preserved = "# Keep these bytes intact\r\n"
            ."export AI_TIMEWEB_LOCAL_RU_API_KEY = 'existing-timeweb-secret' # retained\r\n"
            ."GOODS_SEO_AI_API_KEY=\"\"\r\n"
            ."AI_SALES_ENABLED=false\r\nAI_TIMEWEB_LOCAL_RU_ENABLED=false\r\n";
        $path = $this->temporaryFile($preserved
            ."export GOODS_SEO_AI_ENABLED = false # disabled\r\n"
            ."GOODS_SEO_AI_MODEL=\"old/model\"\r\n"
            ."GOODS_SEO_AI_TOKEN_PARAMETER=max_completion_tokens\r\n"
            ."GOODS_SEO_AI_TIMEOUT_SECONDS=60\r\n");
        $owner = fileowner($path);
        $group = filegroup($path);

        $process = $this->runUpdater($path);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $contents = file_get_contents($path);
        $this->assertStringStartsWith($preserved, $contents);
        $this->assertSame('true', Dotenv::parse($contents)['GOODS_SEO_AI_ENABLED']);
        $this->assertSame('yandex/yandexgpt-pro-5.1', Dotenv::parse($contents)['GOODS_SEO_AI_MODEL']);
        $this->assertSame('max_tokens', Dotenv::parse($contents)['GOODS_SEO_AI_TOKEN_PARAMETER']);
        $this->assertSame('45', Dotenv::parse($contents)['GOODS_SEO_AI_TIMEOUT_SECONDS']);
        $this->assertStringNotContainsString('existing-timeweb-secret', $process->getOutput().$process->getErrorOutput());
        clearstatcache(true, $path);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame($owner, fileowner($path));
        $this->assertSame($group, filegroup($path));

        $this->assertSame(0, $this->runUpdater($path)->getExitCode());
        $this->assertSame($contents, file_get_contents($path));
    }

    public function test_check_mode_validates_without_mutating_the_environment_or_permissions(): void
    {
        $contents = "AI_TIMEWEB_LOCAL_RU_API_KEY='existing-timeweb-secret'\nGOODS_SEO_AI_ENABLED=false\n";
        $path = $this->temporaryFile($contents);
        chmod($path, 0640);

        $process = $this->runUpdater($path, true);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame($contents, file_get_contents($path));
        clearstatcache(true, $path);
        $this->assertSame(0640, fileperms($path) & 0777);
        $this->assertStringNotContainsString('existing-timeweb-secret', $process->getOutput().$process->getErrorOutput());
    }

    public function test_dedicated_key_is_preserved_and_missing_settings_are_appended(): void
    {
        $contents = "GOODS_SEO_AI_API_KEY='dedicated-secret'";
        $path = $this->temporaryFile($contents);

        $process = $this->runUpdater($path);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringStartsWith($contents."\n", file_get_contents($path));
        $this->assertSame('true', Dotenv::parse(file_get_contents($path))['GOODS_SEO_AI_ENABLED']);
        $this->assertStringNotContainsString('dedicated-secret', $process->getOutput().$process->getErrorOutput());
    }

    #[DataProvider('invalidEnvironments')]
    public function test_invalid_environment_fails_before_mutation_and_does_not_leak_secrets(string $contents): void
    {
        $path = $this->temporaryFile($contents);

        foreach ([true, false] as $checkOnly) {
            $process = $this->runUpdater($path, $checkOnly);

            $this->assertNotSame(0, $process->getExitCode());
            $this->assertSame($contents, file_get_contents($path));
            $this->assertStringNotContainsString('sensitive-secret', $process->getOutput().$process->getErrorOutput());
        }
    }

    public static function invalidEnvironments(): array
    {
        return [
            'missing key' => ["APP_ENV=production\nGOODS_SEO_AI_ENABLED=false\n"],
            'blank keys' => ["GOODS_SEO_AI_API_KEY=\"\"\nAI_TIMEWEB_LOCAL_RU_API_KEY=null\n"],
            'invalid dedicated key takes precedence' => ["GOODS_SEO_AI_API_KEY='sensitive-secret with spaces'\nAI_TIMEWEB_LOCAL_RU_API_KEY=valid-fallback\n"],
            'dotenv syntax error' => ["AI_TIMEWEB_LOCAL_RU_API_KEY=\"sensitive-secret\n"],
            'boolean is not a credential' => ["GOODS_SEO_AI_API_KEY=true\n"],
        ];
    }

    public function test_symlink_environment_is_rejected_without_changing_its_target(): void
    {
        $contents = "AI_TIMEWEB_LOCAL_RU_API_KEY=sensitive-secret\n";
        $path = $this->temporaryFile($contents);
        $link = $this->temporaryFile('');
        unlink($link);
        symlink($path, $link);

        $process = $this->runUpdater($link);

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertSame($contents, file_get_contents($path));
        $this->assertStringNotContainsString('sensitive-secret', $process->getOutput().$process->getErrorOutput());
    }

    private function runUpdater(string $path, bool $checkOnly = false): Process
    {
        $arguments = [PHP_BINARY, '-d', 'zend.exception_ignore_args=0', base_path('scripts/update-production-goods-seo-ai-env.php'), $path];
        if ($checkOnly) {
            $arguments[] = '--check';
        }
        $process = new Process($arguments);
        $process->run();

        return $process;
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pischeprom-goods-seo-env-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);
        chmod($path, 0600);
        $this->temporaryPaths[] = $path;

        return $path;
    }
}
