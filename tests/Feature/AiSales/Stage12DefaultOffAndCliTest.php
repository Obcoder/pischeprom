<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\OutreachFeatureGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class Stage12DefaultOffAndCliTest extends Stage12TestCase
{
    public function test_dispatch_is_impossible_even_if_runtime_flags_are_misconfigured_true(): void
    {
        config()->set([
            'ai-sales.outreach_sending_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => true,
            'ai-sales.outreach.auto_send_enabled' => true,
        ]);

        $this->assertFalse(app(OutreachFeatureGuard::class)->dispatchAllowed());
        $this->assertFalse(app(OutreachFeatureGuard::class)->state()['dispatch']);
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $this->assertFalse($routes->contains(fn ($route) => str_contains($route->uri(), 'outreach')
            && preg_match('/(?:send|dispatch|execute)$/', $route->uri())));
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_synthetic_command_rolls_back_and_has_zero_egress_or_mail(): void
    {
        $this->artisan('ai-sales:run-synthetic-outreach-draft')
            ->expectsOutputToContain('APP_ENV=testing')
            ->expectsOutputToContain('CONFIGURED_DB_CONNECTION=sqlite')
            ->expectsOutputToContain('DB_DRIVER=sqlite')
            ->expectsOutputToContain('emails_sent=0 http_requests=0 queue_jobs=0 entities=0')
            ->expectsOutputToContain('all fictional rows rolled back')
            ->assertSuccessful();

        $this->assertDatabaseCount('outreach_drafts', 0);
        $this->assertDatabaseCount('communication_permissions', 0);
        $this->assertDatabaseCount('communication_suppressions', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_synthetic_command_rejects_default_mysql_before_opening_a_connection(): void
    {
        $originalConnection = config('database.default');

        try {
            config()->set('database.default', 'mysql');

            $this->artisan('ai-sales:run-synthetic-outreach-draft')
                ->expectsOutputToContain('APP_ENV=testing')
                ->expectsOutputToContain('CONFIGURED_DB_CONNECTION=mysql')
                ->expectsOutputToContain('default MySQL is never connected')
                ->assertFailed();
        } finally {
            config()->set('database.default', $originalConnection);
        }

        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
