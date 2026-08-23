<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PlanSyntheticProspectingSearchCommand extends Command
{
    protected $signature = 'ai-sales:plan-synthetic-search
        {job : Existing repository-owned prospecting Job UUID}
        {--actor-id= : Existing authorized reviewer user ID}';

    protected $description = 'Plan a repository-owned Product-first synthetic search without HTTP';

    public function handle(PlanProspectingQueries $service): int
    {
        if (! $this->safeEnvironment()) {
            $this->components->error('Synthetic search planning requires local/testing isolated SQLite.');

            return self::FAILURE;
        }
        Http::preventStrayRequests();
        $actorId = filter_var($this->option('actor-id'), FILTER_VALIDATE_INT);
        if (! $actorId) {
            $this->components->error('--actor-id is required.');

            return self::INVALID;
        }
        $this->enableSyntheticFlags();
        $job = ProspectingSearchJob::query()->where('public_id', (string) $this->argument('job'))->firstOrFail();
        $queries = $service->handle($job, User::query()->findOrFail($actorId));
        $this->line(json_encode([
            'environment' => app()->environment(),
            'database_driver' => DB::connection()->getDriverName(),
            'job' => $job->public_id,
            'planned_queries' => $queries->count(),
            'plan_hash' => $queries->first()?->plan_hash,
            'http_requests' => 0,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function safeEnvironment(): bool
    {
        return app()->environment(['local', 'testing'])
            && DB::connection()->getDriverName() === 'sqlite';
    }

    private function enableSyntheticFlags(): void
    {
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
        ]);
    }
}
