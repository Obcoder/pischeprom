<?php

namespace Tests\Unit\Avito;

use PHPUnit\Framework\TestCase;

use function AvitoProductionCheck\inspect;
use function AvitoProductionCheck\safeDiagnostics;
use function AvitoProductionCheck\subscriptionSummary;
use function AvitoProductionCheck\workerSummary;

require_once dirname(__DIR__, 3).'/scripts/check-production-avito.php';

class AvitoProductionCheckTest extends TestCase
{
    public function test_provider_report_only_counts_the_current_protected_callback(): void
    {
        $secret = 'private-value-never-print';
        $base = 'https://shop.example/api/avito/webhook';
        $result = subscriptionSummary([
            ['url' => $base.'?secret='.$secret],
            ['url' => $base.'?secret=old-private-secret'],
            ['url' => $base.'?secret[]=invalid'],
            ['url' => 'https://shop.example.evil.test/api/avito/webhook?secret='.$secret],
            ['url' => 'https://shop.example:8443/api/avito/webhook?secret='.$secret],
            ['url' => 'https://user:pass@shop.example/api/avito/webhook?secret='.$secret],
            ['url' => 'http://shop.example/api/avito/webhook?secret='.$secret],
            ['url' => ['invalid']],
        ], $base, $secret);

        $this->assertSame(['subscription_count' => 8, 'matching_endpoint_count' => 3, 'matching_current_secret_count' => 1], $result);
        $this->assertStringNotContainsString($secret, json_encode($result));
        $this->assertStringNotContainsString('shop.example', json_encode($result));
    }

    public function test_diagnostics_output_excludes_account_names_and_messages(): void
    {
        $result = safeDiagnostics([
            'mode' => 'active', 'response_mode' => 'assistant',
            'blockers' => [['code' => 'write_scope_unconfirmed', 'message' => 'Private name email@example.test']],
            'warnings' => [['code' => 'email@example.test', 'message' => 'secret-value']],
            'accounts' => [['id' => 2, 'name' => 'Private account name', 'sync_enabled' => true, 'sync_status' => 'success', 'credentials_ready' => true, 'write_scope_present' => null, 'access_token' => 'private-token']],
            'raw_exception' => 'private-exception', 'rules' => ['total' => 8, 'response_text' => 'Private answer'],
        ]);

        $this->assertSame('active', $result['mode']);
        $this->assertSame(['write_scope_unconfirmed'], $result['blockers']);
        $this->assertSame(['unknown'], $result['warnings']);
        $this->assertSame(['total' => 8], $result['rule_counts']);
        foreach (['Private', 'private-', 'email@', 'secret-value', 'response_text', 'access_token', 'raw_exception'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, json_encode($result));
        }
    }

    public function test_workers_are_matched_by_connection_and_queue_without_exposing_arguments(): void
    {
        $defaults = ['redis' => 'default', 'database' => 'default'];
        $default = workerSummary(['/usr/bin/php', '/srv/shop/artisan', 'queue:work', '--timeout=1800', '--unrelated-secret=private-value'], 'redis', $defaults);
        $this->assertTrue($default['consumes_avito_queue']);
        $this->assertSame(1800, $default['worker_default_timeout_seconds']);
        $this->assertStringNotContainsString('private-value', json_encode($default));
        $this->assertStringNotContainsString('/srv/shop', json_encode($default));

        $realtime = workerSummary(['php', 'artisan', 'queue:work', 'database', '--queue=realtime', '--timeout', '30'], 'redis', $defaults);
        $this->assertFalse($realtime['consumes_avito_queue']);
        $this->assertTrue($realtime['consumes_realtime_queue']);
        $this->assertSame(30, $realtime['worker_default_timeout_seconds']);

        $mail = workerSummary(['php', 'artisan', 'queue:work', '--queue', 'mail-sync,mail-notifications'], 'redis', $defaults);
        $this->assertFalse($mail['consumes_avito_queue']);
        $this->assertNull(workerSummary(['sh', '-c', 'php artisan queue:work'], 'redis', $defaults));
        $this->assertNull(workerSummary(['php', 'artisan', 'schedule:run'], 'redis', $defaults));
    }

    public function test_partial_inspection_is_not_promoted_to_complete_and_exception_is_hidden(): void
    {
        $this->assertSame(['inspection' => 'partial'], inspect(static fn () => ['inspection' => 'partial']));
        $this->assertSame(['inspection' => 'unavailable'], inspect(static fn () => throw new \RuntimeException('secret-value')));
    }
}
