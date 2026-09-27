<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MailSafetyEnvironmentTest extends TestCase
{
    public function test_update_disables_all_price_list_outbound_gates_without_changing_credentials(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mail-safety-test-');
        $root = dirname(__DIR__, 2);
        $contents = "APP_KEY=synthetic\nYANDEX_AI_API_KEY=preserve-this\nOTHER_FLAG=true\n"
            ."AI_PRICE_LIST_AI_ENABLED=true\n export AI_PRICE_LIST_NOTIFICATIONS_ENABLED = true\n"
            ."AI_PRICE_LIST_AI_ENABLED=\"true\"\nAI_PRICE_LIST_AUTHORIZATION_ENABLED=false\n";
        file_put_contents($file, $contents);

        try {
            $process = new Process([PHP_BINARY, $root.'/scripts/update-production-mail-safety-env.php', $file]);
            $process->mustRun();
            $first = file_get_contents($file);
            $values = \Dotenv\Dotenv::parse($first);
            foreach (['AI_PRICE_LIST_AI_ENABLED', 'AI_PRICE_LIST_NOTIFICATIONS_ENABLED', 'AI_PRICE_LIST_MAX_ACK_ENABLED', 'PRICE_LIST_AI_RERANKING_ENABLED'] as $key) {
                self::assertSame('false', $values[$key]);
            }
            self::assertSame('true', $values['AI_PRICE_LIST_AUTHORIZATION_ENABLED']);
            self::assertSame('preserve-this', $values['YANDEX_AI_API_KEY']);
            self::assertSame('synthetic', $values['APP_KEY']);
            self::assertSame('true', $values['OTHER_FLAG']);
            self::assertStringNotContainsString('preserve-this', $process->getOutput().$process->getErrorOutput());
            $process->mustRun();
            self::assertSame($first, file_get_contents($file));
        } finally {
            unlink($file);
        }
    }
}
