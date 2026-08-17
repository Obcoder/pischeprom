<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Domain\AiSales\Search\SearchProviderException;
use App\Models\ProspectingSearchJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FindBuyersCanaryEnvironmentGuard
{
    /** @var list<string> */
    private const DEFAULT_OFF_KEYS = [
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
        'ai-sales.prospecting.live_probe_enabled',
        'ai-sales.prospecting.query_planning_enabled',
        'ai-sales.prospecting.search_execution_enabled',
        'ai-sales.prospecting.existing_yandex_provider_enabled',
        'ai-sales.prospecting.page_fetch_enabled',
        'ai-sales.prospecting.auto_candidate_ingestion_enabled',
        'ai-sales.prospecting.public_research_enabled',
        'ai-sales.prospecting.scoring_enabled',
        'ai-sales.prospecting.auto_scoring_enabled',
        'ai-sales.prospecting.ai_evidence_enabled',
        'ai-sales.prospecting.live_scoring_enabled',
        'ai-sales.find_buyers.ui_enabled',
        'ai-sales.find_buyers.drafts_enabled',
        'ai-sales.find_buyers.live_execution_enabled',
        'ai-sales.find_buyers.auto_research_enabled',
        'ai-sales.find_buyers.auto_scoring_enabled',
        'ai-sales.providers.timeweb.enabled',
        'ai-sales.providers.timeweb.routes.local_ru.enabled',
        'ai-sales.providers.timeweb.routes.external_sanitized.enabled',
        'ai-sales.providers.timeweb.probe.enabled',
    ];

    public function assertEnvironmentAndDatabase(): string
    {
        if (! app()->environment(['testing', 'staging'])) {
            throw new SearchProviderException('canary_policy', 'stage11b_testing_or_staging_environment_required');
        }
        if (app()->configurationIsCached()) {
            throw new SearchProviderException('canary_policy', 'stage11b_config_cache_blocked');
        }

        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");
        $configuredPath = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $driver !== 'sqlite'
            || $configuredPath === '' || $configuredPath === ':memory:') {
            throw new SearchProviderException('canary_policy', 'stage11b_file_sqlite_required');
        }

        $path = realpath($configuredPath);
        if ($path === false || ! is_file($path) || ! is_writable($path)
            || ! str_starts_with(basename($path), 'pischeprom-stage11b-')) {
            throw new SearchProviderException('canary_policy', 'stage11b_temp_database_path_blocked');
        }
        $roots = array_values(array_filter(array_unique([
            realpath(sys_get_temp_dir()),
            realpath('/tmp'),
        ])));
        if (! collect($roots)->contains(
            fn (string $root): bool => str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR),
        )) {
            throw new SearchProviderException('canary_policy', 'stage11b_temp_database_path_blocked');
        }
        if ((fileperms($path) & 0077) !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_database_permissions_blocked');
        }

        return $path;
    }

    public function assertDefaultOffState(): void
    {
        foreach (self::DEFAULT_OFF_KEYS as $key) {
            if ((bool) config($key, false)) {
                throw new SearchProviderException('canary_policy', 'stage11b_default_off_required');
            }
        }
        if (config('ai-sales.transport_mode') !== 'fake_only'
            || (int) config('ai-sales.limits.max_retries', 0) !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_default_off_required');
        }
    }

    public function assertSyntheticDatabase(ProspectingSearchJob $job): void
    {
        foreach ([
            'users', 'products', 'goods', 'units', 'entities',
            'prospecting_search_jobs', 'prospecting_search_queries',
            'prospecting_search_executions', 'prospecting_search_results',
            'prospecting_public_fetches', 'prospecting_candidates',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new SearchProviderException('canary_policy', 'stage11b_migrations_missing');
            }
        }

        foreach (['units', 'entities', 'sales', 'purchases', 'mails', 'sendings', 'communications', 'contacts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->limit(1)->exists()) {
                throw new SearchProviderException('canary_policy', 'stage11b_business_data_blocked');
            }
        }
        if (ProspectingSearchJob::query()->count() !== 1
            || ! ProspectingSearchJob::query()->whereKey($job->id)->exists()
            || DB::table('users')->count() > 2
            || DB::table('products')->count() > 3
            || DB::table('goods')->count() > 1) {
            throw new SearchProviderException('canary_policy', 'stage11b_non_synthetic_database_blocked');
        }
        foreach ([
            'prospecting_search_executions', 'prospecting_search_results',
            'prospecting_public_fetches', 'prospecting_candidates',
        ] as $table) {
            if (DB::table($table)->limit(1)->exists()) {
                throw new SearchProviderException('canary_policy', 'stage11b_canary_already_executed');
            }
        }
    }
}
