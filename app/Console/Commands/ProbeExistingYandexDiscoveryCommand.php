<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Probes\ExistingYandexDiscoveryProbe;
use App\Domain\AiSales\Probes\ExistingYandexProbeHttpGuard;
use App\Domain\AiSales\Probes\ExistingYandexSecretExposureScanner;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Infrastructure\AiSales\Search\ExistingYandexSearchProviderAdapter;
use App\Services\YandexSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProbeExistingYandexDiscoveryCommand extends Command
{
    protected $signature = 'ai-sales:probe-existing-yandex-discovery
        {--dry-run : Validate and plan the repository-owned scenario without HTTP (default)}
        {--live : Permit the bounded live HTTP path when every gate passes}
        {--yes : Explicitly confirm the live synthetic-only probe}
        {--retain-db : Retain the isolated SQLite file for owner-approved debugging}';

    protected $description = 'Run the bounded repository-owned Stage 09B existing Yandex discovery acceptance probe';

    public function handle(
        ExistingYandexDiscoveryProbe $probe,
        ExistingYandexSecretExposureScanner $scanner,
        SearchProviderRegistry $providers,
        YandexSearchService $yandexSearch,
    ): int {
        $live = (bool) $this->option('live');
        $explicitDryRun = (bool) $this->option('dry-run');
        $retainDatabase = (bool) $this->option('retain-db');
        $guard = new ExistingYandexProbeHttpGuard;
        $databasePath = null;

        try {
            if ($live && $explicitDryRun) {
                throw new SearchProviderException('probe_policy', 'stage09b_probe_mode_conflict');
            }
            if ($live && ! (bool) $this->option('yes')) {
                throw new SearchProviderException('probe_policy', 'stage09b_explicit_confirmation_required');
            }
            if (! app()->environment('testing')) {
                throw new SearchProviderException('probe_policy', 'stage09b_testing_environment_required');
            }
            if ($live && ! (bool) config('ai-sales.prospecting.live_probe_enabled', false)) {
                throw new SearchProviderException('probe_policy', 'stage09b_live_probe_disabled');
            }
            if ($live && app()->configurationIsCached()) {
                throw new SearchProviderException('probe_policy', 'stage09b_config_cache_blocked');
            }

            $databasePath = $this->assertIsolatedDatabase();
            $this->assertDefaultOffState();
            $worktreeState = $scanner->assertExpectedWorktree();
            $apiKey = (string) config('services.yandex_search.api_key');
            $folderId = (string) config('services.yandex_search.folder_id');
            if ($apiKey === '' || $folderId === '') {
                throw new SearchProviderException('configuration', 'stage09b_yandex_not_configured');
            }
            if (! $yandexSearch->configuredHostIsAllowlisted()) {
                throw new SearchProviderException('configuration', 'stage09b_yandex_host_blocked');
            }
            if (! $providers->get('existing_yandex') instanceof ExistingYandexSearchProviderAdapter) {
                throw new SearchProviderException('configuration', 'stage09b_existing_yandex_adapter_required');
            }

            $scan = $scanner->assertSecretAbsent($apiKey);
            $this->assertSyntheticDatabaseEmpty();
            $keySuffix = substr(hash_hmac(
                'sha256',
                $apiKey,
                'stage09b-existing-yandex-readiness-v1',
            ), -12);

            $this->line($this->safeJson([
                'phase' => 'preflight',
                'mode' => $live ? 'live' : 'dry_run',
                'scenario' => ExistingYandexDiscoveryProbe::SCENARIO,
                'environment' => app()->environment(),
                'database_driver' => DB::connection()->getDriverName(),
                'temp_database_path' => $databasePath,
                'default_mysql_selected' => false,
                'default_mysql_connected' => false,
                'synthetic_database_empty' => true,
                'real_domain_rows_read' => 0,
                'config_cached' => app()->configurationIsCached(),
                'worktree_state' => $worktreeState,
                'yandex_key_configured' => true,
                'yandex_folder_configured' => true,
                'yandex_host_profile' => 'allowlisted_existing_yandex',
                'yandex_key_hmac_suffix' => $keySuffix,
                'secret_scan' => $scan,
                'caps' => [
                    'yandex_search_requests' => 1,
                    'normalized_results' => ExistingYandexDiscoveryProbe::MAX_RESULTS,
                    'fetch_domains' => ExistingYandexDiscoveryProbe::MAX_FETCH_DOMAINS,
                    'successful_pages' => ExistingYandexDiscoveryProbe::MAX_SUCCESSFUL_PAGES,
                    'total_live_http' => ExistingYandexProbeHttpGuard::MAX_TOTAL_REQUESTS,
                    'candidates' => ExistingYandexDiscoveryProbe::MAX_CANDIDATES,
                    'redirects_per_page' => 1,
                    'retries' => 0,
                    'failovers' => 0,
                ],
                'ordinary_unit_ai_runtime' => false,
                'timeweb_live_enabled' => false,
            ]));

            $this->enableProcessLocalProbeFlags();
            Http::globalRequestMiddleware(fn ($request) => $guard->authorize($request));
            $result = $probe->run($live, $guard);
            $findings = $scanner->databaseFindings($apiKey);
            if (in_array(true, $findings, true)) {
                throw new SearchProviderException('security', 'stage09b_unsafe_persistence_detected');
            }
            $this->resetProcessLocalFlags();

            $this->line($this->safeJson([
                'phase' => 'result',
                ...$result,
                ...$guard->summary(),
                'cost_status' => $live ? 'unknown_bounded_by_one_search_request' : 'zero_no_http',
                'persistence_findings' => $findings,
                'database_retained' => $retainDatabase,
                'final_state' => $this->finalState(),
            ]));

            return self::SUCCESS;
        } catch (SearchProviderException $exception) {
            $this->resetProcessLocalFlags();
            $status = $exception->category === 'security' ? 'STOP_SECURITY' : 'blocked';
            $this->line($this->safeJson([
                'phase' => 'blocked',
                'status' => $status,
                'safe_category' => $exception->category,
                'safe_code' => $exception->safeCode,
                ...$guard->summary(),
                'raw_body_printed' => false,
                'secret_printed' => false,
                'final_state' => $this->finalState(),
            ]));

            return self::FAILURE;
        } catch (Throwable) {
            $this->resetProcessLocalFlags();
            $this->line($this->safeJson([
                'phase' => 'blocked',
                'status' => 'blocked',
                'safe_category' => 'internal',
                'safe_code' => 'stage09b_probe_failed_safely',
                ...$guard->summary(),
                'raw_body_printed' => false,
                'secret_printed' => false,
                'final_state' => $this->finalState(),
            ]));

            return self::FAILURE;
        } finally {
            $this->resetProcessLocalFlags();
            if (! $retainDatabase && is_string($databasePath)) {
                $this->deleteIsolatedDatabase($databasePath);
            }
        }
    }

    private function assertIsolatedDatabase(): string
    {
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");
        $configuredPath = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $driver !== 'sqlite'
            || $configuredPath === '' || $configuredPath === ':memory:') {
            throw new SearchProviderException('probe_policy', 'stage09b_file_sqlite_required');
        }

        $path = realpath($configuredPath);
        if ($path === false || ! is_file($path) || ! is_writable($path)
            || ! str_starts_with(basename($path), 'pischeprom-stage09b-')) {
            throw new SearchProviderException('probe_policy', 'stage09b_temp_database_path_blocked');
        }
        $roots = array_values(array_filter(array_unique([
            realpath(sys_get_temp_dir()),
            realpath('/tmp'),
        ])));
        if (! collect($roots)->contains(fn (string $root): bool => str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
            throw new SearchProviderException('probe_policy', 'stage09b_temp_database_path_blocked');
        }

        return $path;
    }

    private function assertSyntheticDatabaseEmpty(): void
    {
        foreach ([
            'users', 'products', 'goods', 'units', 'entities',
            'product_search_requests', 'product_search_results',
            'prospecting_search_jobs', 'prospecting_search_queries',
            'prospecting_search_executions', 'prospecting_search_results',
            'prospecting_candidates',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new SearchProviderException('probe_policy', 'stage09b_migrations_missing');
            }
            if (DB::table($table)->limit(1)->exists()) {
                throw new SearchProviderException('probe_policy', 'stage09b_non_synthetic_database_blocked');
            }
        }
    }

    private function assertDefaultOffState(): void
    {
        $falseKeys = [
            'ai-sales.enabled',
            'ai-sales.external_calls_enabled',
            'ai-sales.local_ru_calls_enabled',
            'ai-sales.external_sanitized_calls_enabled',
            'ai-sales.provider_failover_enabled',
            'ai-sales.web_search_enabled',
            'ai-sales.outreach_drafting_enabled',
            'ai-sales.outreach_sending_enabled',
            'ai-sales.autonomous_campaigns_enabled',
            'ai-sales.provider_native_tools_enabled',
            'ai-sales.live_business_workflows_enabled',
            'ai-sales.prospecting.dossier_enabled',
            'ai-sales.prospecting.jobs_enabled',
            'ai-sales.prospecting.candidate_import_enabled',
            'ai-sales.prospecting.auto_create_unit',
            'ai-sales.prospecting.live_search_enabled',
            'ai-sales.prospecting.query_planning_enabled',
            'ai-sales.prospecting.search_execution_enabled',
            'ai-sales.prospecting.existing_yandex_provider_enabled',
            'ai-sales.prospecting.page_fetch_enabled',
            'ai-sales.prospecting.auto_candidate_ingestion_enabled',
            'ai-sales.prospecting.public_research_enabled',
            'ai-sales.providers.timeweb.enabled',
            'ai-sales.providers.timeweb.routes.local_ru.enabled',
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled',
            'ai-sales.providers.timeweb.probe.enabled',
        ];
        foreach ($falseKeys as $key) {
            if ((bool) config($key, false)) {
                throw new SearchProviderException('probe_policy', 'stage09b_default_off_required');
            }
        }
        if (config('ai-sales.transport_mode') !== 'fake_only'
            || (int) config('ai-sales.limits.max_retries', 0) !== 0) {
            throw new SearchProviderException('probe_policy', 'stage09b_default_off_required');
        }
    }

    private function enableProcessLocalProbeFlags(): void
    {
        config()->set([
            'cache.default' => 'array',
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.prospecting.limits.max_queries' => 1,
            'ai-sales.prospecting.limits.max_candidates' => 1,
            'ai-sales.prospecting.limits.max_search_requests_per_job' => 1,
            'ai-sales.prospecting.limits.max_search_results_per_query' => 10,
            'ai-sales.prospecting.limits.max_public_fetches_per_domain' => 1,
            'ai-sales.prospecting.limits.max_public_fetch_redirects' => 1,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.queue.connection' => 'sync',
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    private function resetProcessLocalFlags(): void
    {
        config()->set([
            'ai-sales.enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.prospecting.dossier_enabled' => false,
            'ai-sales.prospecting.jobs_enabled' => false,
            'ai-sales.prospecting.candidate_import_enabled' => false,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.prospecting.live_probe_enabled' => false,
            'ai-sales.prospecting.query_planning_enabled' => false,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function finalState(): array
    {
        return [
            'ai_sales_enabled' => (bool) config('ai-sales.enabled', false),
            'transport_mode' => config('ai-sales.transport_mode'),
            'query_planning' => (bool) config('ai-sales.prospecting.query_planning_enabled', false),
            'search_execution' => (bool) config('ai-sales.prospecting.search_execution_enabled', false),
            'existing_yandex_provider' => (bool) config('ai-sales.prospecting.existing_yandex_provider_enabled', false),
            'page_fetch' => (bool) config('ai-sales.prospecting.page_fetch_enabled', false),
            'auto_candidate_ingestion' => (bool) config('ai-sales.prospecting.auto_candidate_ingestion_enabled', false),
            'public_research' => (bool) config('ai-sales.prospecting.public_research_enabled', false),
            'timeweb' => (bool) config('ai-sales.providers.timeweb.enabled', false),
            'probe' => (bool) config('ai-sales.prospecting.live_probe_enabled', false),
            'retries' => (int) config('ai-sales.limits.max_retries', 0),
            'failover' => (bool) config('ai-sales.provider_failover_enabled', false),
            'kill_switches' => 'blocking',
        ];
    }

    private function deleteIsolatedDatabase(string $path): void
    {
        DB::disconnect('sqlite');
        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function safeJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );
    }
}
