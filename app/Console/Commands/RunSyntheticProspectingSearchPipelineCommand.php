<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Domain\AiSales\Services\ExecuteProspectingSearchJob;
use App\Infrastructure\AiSales\Search\FakeSearchProvider;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RunSyntheticProspectingSearchPipelineCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-search-pipeline
        {job : Existing repository-owned prospecting Job UUID with an approved plan}
        {--actor-id= : Existing authorized reviewer user ID}';

    protected $description = 'Run the Product-first search pipeline with repository fake results and zero HTTP';

    public function handle(ExecuteProspectingSearchJob $service): int
    {
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('Synthetic search execution requires local/testing isolated SQLite.');

            return self::FAILURE;
        }
        $actorId = filter_var($this->option('actor-id'), FILTER_VALIDATE_INT);
        if (! $actorId) {
            $this->components->error('--actor-id is required.');

            return self::INVALID;
        }
        Http::preventStrayRequests();
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.queue.connection' => 'sync',
        ]);
        app()->forgetInstance(SearchProviderRegistry::class);
        app()->instance(SearchProviderRegistry::class, new SearchProviderRegistry([new FakeSearchProvider]));
        $job = ProspectingSearchJob::query()->where('public_id', (string) $this->argument('job'))->firstOrFail();
        $service->handle($job, User::query()->findOrFail($actorId));
        $job->refresh();
        $this->line(json_encode([
            'environment' => app()->environment(),
            'database_driver' => DB::connection()->getDriverName(),
            'job' => $job->public_id,
            'executions' => $job->searchExecutions()->count(),
            'results' => $job->searchResults()->count(),
            'http_requests' => 0,
            'retries' => 0,
            'failovers' => 0,
            'auto_candidate_ingestion' => false,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
