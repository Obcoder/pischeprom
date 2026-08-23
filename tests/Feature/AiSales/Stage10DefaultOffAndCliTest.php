<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Stage10DefaultOffAndCliTest extends TestCase
{
    public function test_stage10_flags_are_default_off_and_synthetic_cli_has_no_http_or_persistence(): void
    {
        foreach (['scoring_enabled', 'auto_scoring_enabled', 'score_overrides_enabled', 'ai_evidence_enabled', 'live_scoring_enabled'] as $flag) {
            $this->assertFalse((bool) config('ai-sales.prospecting.'.$flag));
        }
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));
        $this->assertFalse((bool) config('ai-sales.external_calls_enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        Http::preventStrayRequests();
        $this->artisan('ai-sales:score-synthetic-prospect', ['scenario' => 'all'])
            ->expectsOutputToContain('HTTP=0')
            ->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_recalculation_cli_is_blocked_outside_local_testing_or_staging(): void
    {
        $original = app()->environment();
        try {
            app()->detectEnvironment(static fn (): string => 'production');
            $this->artisan('ai-sales:recalculate-prospecting-scores', ['--user-id' => 1])
                ->expectsOutputToContain('never runs in production')
                ->assertFailed();

            app()->detectEnvironment(static fn (): string => 'acceptance');
            $this->artisan('ai-sales:recalculate-prospecting-scores', ['--user-id' => 1])
                ->expectsOutputToContain('restricted to local/testing/staging')
                ->assertFailed();
        } finally {
            app()->detectEnvironment(static fn () => $original);
        }
        Http::assertNothingSent();
    }
}
