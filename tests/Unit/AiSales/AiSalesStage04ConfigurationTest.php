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
        $this->assertFalse(config('ai-sales.providers.timeweb.enabled'));
        $this->assertFalse(config('ai-sales.providers.timeweb.routes.local_ru.enabled'));
        $this->assertFalse(config('ai-sales.providers.timeweb.routes.external_sanitized.enabled'));
        $this->assertFalse(config('ai-sales.providers.timeweb.probe.enabled'));
        $this->assertTrue(config('ai-sales.providers.timeweb.probe.synthetic_only'));
        $this->assertTrue(
            config('ai-sales.providers.timeweb.routes.local_ru.api_key') === '',
            'The Timeweb local route key must be absent from the test environment.',
        );
        $this->assertTrue(
            config('ai-sales.providers.timeweb.routes.external_sanitized.api_key') === '',
            'The Timeweb external route key must be absent from the test environment.',
        );
        $this->assertSame([], config('ai-sales.providers.timeweb.routes.local_ru.model_ids'));
        $this->assertSame('https://api.timeweb.ai/v1', config('ai-sales.providers.timeweb.base_url'));
        $this->assertSame([], $this->nonCacheableConfigurationPaths((array) config('ai-sales')));
    }

    public function test_application_registry_contains_fake_provider_contracts_only(): void
    {
        $providers = app(AiProviderRegistry::class)->all();

        $this->assertCount(2, $providers);

        foreach ($providers as $provider) {
            $this->assertInstanceOf(FakeAiProviderInterface::class, $provider);
        }
    }

    private function nonCacheableConfigurationPaths(array $value, string $path = 'ai-sales'): array
    {
        $findings = [];

        foreach ($value as $key => $item) {
            $child = $path.'.'.$key;

            if ($item instanceof \Closure || is_resource($item) || is_object($item)) {
                $findings[] = $child;
            }

            if (is_array($item)) {
                $findings = [...$findings, ...$this->nonCacheableConfigurationPaths($item, $child)];
            }
        }

        return $findings;
    }
}
