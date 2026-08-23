<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OutreachCanaryEnvironmentGuard
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
        'ai-sales.outreach.ui_enabled',
        'ai-sales.outreach.drafts_enabled',
        'ai-sales.outreach.fake_generation_enabled',
        'ai-sales.outreach.permission_ledger_enabled',
        'ai-sales.outreach.suppression_management_enabled',
        'ai-sales.outreach.dispatch_enabled',
        'ai-sales.outreach.live_generation_enabled',
        'ai-sales.outreach.live_synthetic_canary_enabled',
        'ai-sales.outreach.auto_send_enabled',
        'ai-sales.providers.timeweb.enabled',
        'ai-sales.providers.timeweb.routes.local_ru.enabled',
        'ai-sales.providers.timeweb.routes.external_sanitized.enabled',
        'ai-sales.providers.timeweb.probe.enabled',
        'ai-sales.prospecting.live_search_enabled',
        'ai-sales.prospecting.live_probe_enabled',
        'ai-sales.prospecting.search_execution_enabled',
        'ai-sales.prospecting.existing_yandex_provider_enabled',
        'ai-sales.prospecting.page_fetch_enabled',
        'ai-sales.prospecting.public_research_enabled',
        'ai-sales.find_buyers.live_execution_enabled',
    ];

    public function assertEnvironmentAndDatabase(): string
    {
        if (! app()->environment('testing')) {
            throw new PolicyViolation('stage12b_testing_environment_required', 'Stage 12B requires APP_ENV=testing.');
        }
        if (app()->configurationIsCached()) {
            throw new PolicyViolation('stage12b_config_cache_blocked', 'Configuration cache must be absent.');
        }

        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");
        $configuredPath = (string) config("database.connections.{$default}.database");
        if ($default !== 'sqlite' || $driver !== 'sqlite' || $configuredPath === '' || $configuredPath === ':memory:') {
            throw new PolicyViolation('stage12b_file_sqlite_required', 'A file-backed default SQLite connection is required.');
        }

        $path = realpath($configuredPath);
        if ($path === false || ! is_file($path) || ! is_writable($path)
            || ! str_starts_with(basename($path), 'pischeprom-stage12b-')) {
            throw new PolicyViolation('stage12b_temp_database_path_blocked', 'The SQLite path is outside the Stage 12B temporary profile.');
        }
        $roots = array_values(array_filter(array_unique([
            realpath(sys_get_temp_dir()),
            realpath('/tmp'),
        ])));
        if (! collect($roots)->contains(
            fn (string $root): bool => str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR),
        )) {
            throw new PolicyViolation('stage12b_temp_database_path_blocked', 'The SQLite file must be under an OS temporary directory.');
        }
        if ((fileperms($path) & 0077) !== 0) {
            throw new PolicyViolation('stage12b_database_permissions_blocked', 'The SQLite file must use owner-only permissions.');
        }
        if (array_key_exists('mysql', DB::getConnections())) {
            throw new PolicyViolation('stage12b_mysql_connection_detected', 'A MySQL connection was already opened in the canary process.');
        }

        return $path;
    }

    public function assertDefaultOffState(): void
    {
        foreach (self::DEFAULT_OFF_KEYS as $key) {
            if ((bool) config($key, false)) {
                throw new PolicyViolation('stage12b_default_off_required', 'Ordinary AI, outreach, search and provider runtime must start disabled.');
            }
        }
        if (config('ai-sales.transport_mode') !== 'fake_only'
            || config('ai-sales.outreach.transport_mode') !== 'fake_only'
            || (int) config('ai-sales.limits.max_retries', 0) !== 0) {
            throw new PolicyViolation('stage12b_default_off_required', 'Transport, retry and outreach runtime defaults are not closed.');
        }
    }

    public function assertPristineSyntheticDatabase(): void
    {
        foreach ([
            'users', 'products', 'goods', 'units', 'entities', 'emails', 'sendings',
            'authorized_mail_dispatch_attempts', 'outreach_drafts', 'outreach_draft_revisions',
            'outreach_draft_claims', 'outreach_draft_reviews', 'outreach_dispatch_decisions',
            'communication_permissions', 'communication_suppressions', 'ai_provider_models',
            'ai_provider_capabilities', 'ai_provider_pricing_snapshots',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new PolicyViolation('stage12b_migrations_missing', 'The isolated database does not contain the required schema.');
            }
        }

        foreach ([
            'users', 'products', 'goods', 'units', 'entities', 'emails', 'sendings',
            'authorized_mail_dispatch_attempts', 'outreach_drafts', 'outreach_draft_revisions',
            'outreach_draft_claims', 'outreach_draft_reviews', 'outreach_dispatch_decisions',
            'communication_permissions', 'communication_suppressions', 'ai_provider_models',
            'ai_provider_pricing_snapshots',
        ] as $table) {
            if (DB::table($table)->limit(1)->exists()) {
                throw new PolicyViolation('stage12b_non_synthetic_database_blocked', 'The isolated database already contains non-canary data.');
            }
        }

        if (DB::table('ai_provider_capabilities')->where('provider_code', 'timeweb')->exists()) {
            throw new PolicyViolation('stage12b_non_synthetic_database_blocked', 'The isolated database already contains Timeweb evidence rows.');
        }
        if (array_key_exists('mysql', DB::getConnections())) {
            throw new PolicyViolation('stage12b_mysql_connection_detected', 'The canary must not open default MySQL.');
        }
    }
}
