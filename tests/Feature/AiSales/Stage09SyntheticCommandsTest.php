<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Stage09SyntheticCommandsTest extends Stage09TestCase
{
    public function test_synthetic_commands_use_isolated_sqlite_fake_provider_and_zero_http(): void
    {
        $actor = $this->prospectingUser();
        $job = $this->approvedJob($actor);

        $this->artisan('ai-sales:plan-synthetic-search', [
            'job' => $job->public_id,
            '--actor-id' => $actor->id,
        ])->expectsOutputToContain('"http_requests":0')
            ->assertSuccessful();

        app(ApproveProspectingQueryPlan::class)->handle($job->fresh(), $actor);
        $this->artisan('ai-sales:run-synthetic-search-pipeline', [
            'job' => $job->public_id,
            '--actor-id' => $actor->id,
        ])->expectsOutputToContain('"http_requests":0')
            ->assertSuccessful();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.auto_candidate_ingestion_enabled'));
        $this->assertDatabaseCount('prospecting_search_executions', 2);
        $this->assertDatabaseCount('prospecting_search_results', 4);
        Http::assertNothingSent();
    }
}
