<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class ProspectingCommandsTest extends Stage08TestCase
{
    public function test_prune_command_is_dry_run_by_default_and_outputs_safe_counters_only(): void
    {
        $actor = $this->prospectingUser();
        $candidate = $this->candidate($this->approvedJob($actor), $actor, [
            'working_name' => 'DO_NOT_PRINT_CANDIDATE',
            'channels' => [['kind' => 'email', 'value' => 'do-not-print@stage08.example', 'contact_role' => 'person_specific']],
        ]);
        $candidate->update(['expires_at' => now()->subMinute()]);
        $exit = Artisan::call('ai-sales:prune-prospecting-candidates');
        $output = Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('dry-run', $output);
        $this->assertStringNotContainsString('DO_NOT_PRINT_CANDIDATE', $output);
        $this->assertStringNotContainsString('do-not-print@stage08.example', $output);
        $this->assertNull($candidate->fresh()->anonymized_at);
    }

    public function test_repository_owned_synthetic_command_rolls_back_and_sends_no_http(): void
    {
        Http::preventStrayRequests();
        $exit = Artisan::call('ai-sales:run-synthetic-candidate-resolution');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('APP_ENV=testing', $output);
        $this->assertStringContainsString('DB_DRIVER=sqlite', $output);
        $this->assertStringContainsString('HTTP requests: 0', $output);
        $this->assertDatabaseCount('prospecting_candidates', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('entities', 0);
        Http::assertNothingSent();
    }
}
