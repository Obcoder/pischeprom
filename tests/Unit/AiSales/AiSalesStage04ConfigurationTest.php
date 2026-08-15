<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Contracts\FakeAiProviderInterface;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSalesStage04ConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    public function test_all_execution_and_external_feature_flags_default_off(): void
    {
        foreach ([
            'enabled',
            'fake_execution_enabled',
            'external_calls_enabled',
            'local_ru_calls_enabled',
            'external_sanitized_calls_enabled',
            'provider_failover_enabled',
            'web_search_enabled',
            'outreach_drafting_enabled',
            'outreach_sending_enabled',
            'autonomous_campaigns_enabled',
        ] as $flag) {
            $this->assertFalse(config("ai-sales.{$flag}"), $flag);
        }

        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
        $this->assertSame([], $this->forbiddenConfigurationPaths((array) config('ai-sales')));
    }

    public function test_application_registry_contains_fake_provider_contracts_only(): void
    {
        $providers = app(AiProviderRegistry::class)->all();

        $this->assertCount(2, $providers);

        foreach ($providers as $provider) {
            $this->assertInstanceOf(FakeAiProviderInterface::class, $provider);
        }
    }

    private function forbiddenConfigurationPaths(array $value, string $path = 'ai-sales'): array
    {
        $findings = [];

        foreach ($value as $key => $item) {
            $child = $path.'.'.$key;

            if (preg_match('/(?:api[_-]?key|secret|password|token|base[_-]?url|endpoint[_-]?url)$/i', (string) $key)) {
                $findings[] = $child;
            }

            if (is_array($item)) {
                $findings = [...$findings, ...$this->forbiddenConfigurationPaths($item, $child)];
            }
        }

        return $findings;
    }
}
