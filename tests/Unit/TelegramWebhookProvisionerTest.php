<?php

namespace Tests\Unit;

use App\Services\TelegramWebhookProvisioner;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class TelegramWebhookProvisionerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/telegram-provision-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/{*,.*}', GLOB_BRACE) as $path) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_existing_webhook_is_secured_without_changing_url_options_updates_or_unrelated_environment(): void
    {
        $original = "# private config\r\nAPP_URL=https://xn----dtbhbbn3apgclecj7i.xn--p1ai\r\nTELEGRAM_BOT_TOKEN=123:test_token\r\nOTHER_SECRET=\"keep # this\"\r\n";
        file_put_contents($this->directory.'/.env', $original);
        $calls = [];
        $info = $this->info(['url' => 'https://пищепром-сервер.рф/api/webhook', 'allowed_updates' => ['message', 'edited_message'], 'pending_update_count' => 12]);
        $service = new TelegramWebhookProvisioner(function ($token, $method, $parameters) use (&$calls, $info): array {
            $calls[] = [$token, $method, $parameters];
            if ($method === 'setWebhook') {
                self::assertSame($parameters['secret_token'], Dotenv::parse(file_get_contents($this->directory.'/.env'))['TELEGRAM_WEBHOOK_SECRET']);
            }

            return ['ok' => true, 'result' => $method === 'getWebhookInfo' ? $info : true];
        });

        self::assertSame('secured', $service->provision($this->directory));
        $contents = file_get_contents($this->directory.'/.env');
        self::assertStringStartsWith($original, $contents);
        $secret = Dotenv::parse($contents)['TELEGRAM_WEBHOOK_SECRET'];
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $secret);
        self::assertSame(['getWebhookInfo', 'setWebhook'], array_column($calls, 1));
        self::assertSame(['url' => $info['url'], 'drop_pending_updates' => false, 'max_connections' => 40, 'allowed_updates' => ['message', 'edited_message'], 'secret_token' => $secret], $calls[1][2]);
        self::assertSame(0600, fileperms($this->directory.'/.env') & 0777);

        self::assertSame('secured', $service->provision($this->directory));
        self::assertSame($contents, file_get_contents($this->directory.'/.env'));
        self::assertSame($secret, $calls[3][2]['secret_token']);
    }

    public function test_valid_existing_secret_is_reused_and_absent_optional_updates_are_not_reset(): void
    {
        $contents = $this->environment()."TELEGRAM_WEBHOOK_SECRET=existing_valid-secret\n";
        file_put_contents($this->directory.'/.env', $contents);
        $service = new TelegramWebhookProvisioner(function ($token, $method, $parameters): array {
            if ($method === 'setWebhook') {
                self::assertSame('existing_valid-secret', $parameters['secret_token']);
                self::assertArrayNotHasKey('allowed_updates', $parameters);
                self::assertFalse($parameters['drop_pending_updates']);
            }

            return ['ok' => true, 'result' => $method === 'getWebhookInfo' ? $this->info() : true];
        });
        self::assertSame('secured', $service->provision($this->directory));
        self::assertSame($contents, file_get_contents($this->directory.'/.env'));
    }

    public function test_preflight_validates_the_webhook_without_creating_a_secret_or_changing_environment_permissions(): void
    {
        $contents = $this->environment();
        file_put_contents($this->directory.'/.env', $contents);
        chmod($this->directory.'/.env', 0440);
        $calls = [];
        $service = new TelegramWebhookProvisioner(function ($token, $method, $parameters) use (&$calls): array {
            $calls[] = $method;
            self::assertSame('getWebhookInfo', $method);
            self::assertSame([], $parameters);

            return ['ok' => true, 'result' => $this->info()];
        });

        self::assertSame('checked', $service->provision($this->directory, true));
        self::assertSame(['getWebhookInfo'], $calls);
        self::assertSame($contents, file_get_contents($this->directory.'/.env'));
        self::assertSame(0440, fileperms($this->directory.'/.env') & 0777);
    }

    public function test_preflight_transport_failure_stops_without_changing_environment_or_disclosing_credentials(): void
    {
        $contents = $this->environment();
        file_put_contents($this->directory.'/.env', $contents);
        $calls = [];
        $service = new TelegramWebhookProvisioner(function ($token, $method) use (&$calls): never {
            $calls[] = $method;
            throw new RuntimeException('https://api.telegram.org/bot123:test_token/getWebhookInfo');
        });
        try {
            $service->provision($this->directory, true);
            self::fail('An unavailable provider must block preflight.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString('test_token', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame(['getWebhookInfo'], $calls);
            self::assertSame($contents, file_get_contents($this->directory.'/.env'));
        }
    }

    public function test_no_token_or_unregistered_webhook_never_causes_remote_mutation(): void
    {
        file_put_contents($this->directory.'/.env', "APP_URL=https://example.test\n");
        $service = new TelegramWebhookProvisioner(fn () => self::fail('No token must mean no network request.'));
        self::assertSame('disabled', $service->provision($this->directory));

        $contents = $this->environment();
        file_put_contents($this->directory.'/.env', $contents);
        $service = new TelegramWebhookProvisioner(function ($token, $method): array {
            self::assertSame('getWebhookInfo', $method);

            return ['ok' => true, 'result' => $this->info(['url' => ''])];
        });
        self::assertSame('disabled', $service->provision($this->directory));
        self::assertSame($contents, file_get_contents($this->directory.'/.env'));
    }

    #[DataProvider('unsupportedSettings')]
    public function test_unknown_or_foreign_webhook_configuration_is_not_mutated(array $settings): void
    {
        $contents = $this->environment();
        file_put_contents($this->directory.'/.env', $contents);
        $service = new TelegramWebhookProvisioner(function ($token, $method) use ($settings): array {
            self::assertSame('getWebhookInfo', $method);

            return ['ok' => true, 'result' => $this->info($settings)];
        });
        foreach ([false, true] as $checkOnly) {
            try {
                $service->provision($this->directory, $checkOnly);
                self::fail('Unsupported configuration must fail closed.');
            } catch (RuntimeException $exception) {
                self::assertStringNotContainsString('test_token', $exception->getMessage());
                self::assertSame($contents, file_get_contents($this->directory.'/.env'));
            }
        }
    }

    public static function unsupportedSettings(): array
    {
        return [
            'other host' => [['url' => 'https://foreign.test/api/webhook']],
            'wrong path' => [['url' => 'https://example.test/other']],
            'insecure URL' => [['url' => 'http://example.test/api/webhook']],
            'credentials' => [['url' => 'https://private@example.test/api/webhook']],
            'query' => [['url' => 'https://example.test/api/webhook?secret=value']],
            'certificate' => [['has_custom_certificate' => true]],
            'unknown setting' => [['future_configuration' => true]],
            'invalid max' => [['max_connections' => 0]],
            'invalid updates' => [['allowed_updates' => 'message']],
        ];
    }

    public function test_failed_remote_update_keeps_the_new_secret_for_a_safe_retry_and_redacts_transport_errors(): void
    {
        file_put_contents($this->directory.'/.env', $this->environment()."TELEGRAM_WEBHOOK_SECRET=\"invalid secret\"\n");
        $service = new TelegramWebhookProvisioner(function ($token, $method): array {
            if ($method === 'setWebhook') {
                throw new RuntimeException('https://api.telegram.org/bot123:test_token/setWebhook secret leaked');
            }

            return ['ok' => true, 'result' => $this->info()];
        });
        try {
            $service->provision($this->directory);
            self::fail('The failed update must stop deployment.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString('test_token', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', Dotenv::parse(file_get_contents($this->directory.'/.env'))['TELEGRAM_WEBHOOK_SECRET']);
        }
    }

    public function test_cli_reports_only_generic_errors_for_invalid_environment(): void
    {
        symlink(dirname(__DIR__, 2).'/vendor', $this->directory.'/vendor');
        file_put_contents($this->directory.'/.env', "TELEGRAM_BOT_TOKEN=\"private secret\"\n");
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/provision-production-telegram.php', $this->directory]);
        $process->run();
        self::assertSame(1, $process->getExitCode());
        self::assertStringNotContainsString('private secret', $process->getOutput().$process->getErrorOutput());
        self::assertStringContainsString('provisioning failed', $process->getErrorOutput());
    }

    public function test_staged_cli_uses_the_selected_service_and_running_application_dependencies_in_check_mode(): void
    {
        symlink(dirname(__DIR__, 2).'/vendor', $this->directory.'/vendor');
        $contents = "TELEGRAM_BOT_TOKEN=\"invalid token must never reach the old service\"\n";
        file_put_contents($this->directory.'/.env', $contents);
        $stage = $this->directory.'/selected';
        mkdir($stage.'/scripts', 0700, true);
        mkdir($stage.'/app/Services', 0700, true);
        copy(dirname(__DIR__, 2).'/scripts/provision-production-telegram.php', $stage.'/scripts/provision-production-telegram.php');
        file_put_contents($stage.'/app/Services/TelegramWebhookProvisioner.php', <<<'PHP'
<?php
namespace App\Services;
final class TelegramWebhookProvisioner
{
    public function __construct(\Closure $request) {}
    public function provision(string $directory, bool $checkOnly = false): string
    {
        if (! $checkOnly) { throw new \RuntimeException('Expected check mode.'); }
        return 'checked';
    }
}
PHP);
        try {
            $process = new Process([PHP_BINARY, $stage.'/scripts/provision-production-telegram.php', $this->directory, '--check']);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertStringContainsString('preflight passed', $process->getOutput());
            self::assertSame($contents, file_get_contents($this->directory.'/.env'));
        } finally {
            unlink($stage.'/scripts/provision-production-telegram.php');
            unlink($stage.'/app/Services/TelegramWebhookProvisioner.php');
            rmdir($stage.'/scripts');
            rmdir($stage.'/app/Services');
            rmdir($stage.'/app');
            rmdir($stage);
        }
    }

    private function environment(): string
    {
        return "APP_URL=https://example.test\nTELEGRAM_BOT_TOKEN=123:test_token\nOTHER_VALUE=preserved\n";
    }

    private function info(array $overrides = []): array
    {
        return array_replace(['url' => 'https://example.test/api/webhook', 'has_custom_certificate' => false, 'pending_update_count' => 0, 'max_connections' => 40], $overrides);
    }
}
