<?php

namespace Tests\Unit\AiSales;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSalesStage07ConfigurationTest extends TestCase
{
    public function test_stage07_flags_are_forced_default_off_and_no_egress_occurs(): void
    {
        Http::preventStrayRequests();

        $this->assertFalse((bool) config('ai-sales.tools.enabled'));
        $this->assertFalse((bool) config('ai-sales.workflows.enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_native_tools_enabled'));
        $this->assertFalse((bool) config('ai-sales.live_business_workflows_enabled'));
        $this->assertFalse((bool) config('ai-sales.external_calls_enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertFalse((bool) config('ai-sales.providers.timeweb.enabled'));
        $this->assertFalse((bool) config('ai-sales.providers.timeweb.probe.enabled'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
        Http::assertNothingSent();
    }

    public function test_committed_environment_example_contains_only_empty_default_off_stage07_controls(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('AI_TOOLS_ENABLED=false', $example);
        $this->assertStringContainsString('AI_WORKFLOWS_ENABLED=false', $example);
        $this->assertStringContainsString('AI_PROVIDER_NATIVE_TOOLS_ENABLED=false', $example);
        $this->assertStringContainsString('AI_LIVE_BUSINESS_WORKFLOWS_ENABLED=false', $example);
        $this->assertDoesNotMatchRegularExpression('/AI_(?:TIMEWEB|OPENAI|PROXYAPI).*KEY=\S+/', $example);
    }
}
