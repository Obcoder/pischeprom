<?php

namespace App\Console\Commands;

use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryEnvironmentGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryHttpGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryJobGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryRepositoryGuard;
use App\Domain\AiSales\FindBuyers\Canary\FindBuyersCanaryRunner;
use App\Domain\AiSales\FindBuyers\FindBuyersProgressQuery;
use App\Domain\AiSales\Probes\ExistingYandexSecretExposureScanner;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Infrastructure\AiSales\Search\ExistingYandexSearchProviderAdapter;
use App\Models\ProspectingSearchJob;
use App\Services\YandexSearchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RunFindBuyersCanaryCommand extends Command
{
    protected $signature = 'ai-sales:run-find-buyers-canary
        {job : Public UUID of an approved UI-created Find Buyers Job}
        {--dry-run : Revalidate and report the server-owned plan without HTTP (default)}
        {--live : Permit the bounded operator-only live path when every gate passes}
        {--yes : Explicitly confirm the single live canary}
        {--retain-db : Retain the isolated synthetic SQLite file for owner-approved UI inspection}';

    protected $description = 'Run the bounded operator-only Stage 11B Find Buyers staging canary';

    public function handle(
        FindBuyersCanaryEnvironmentGuard $environment,
        FindBuyersCanaryRepositoryGuard $repository,
        FindBuyersCanaryJobGuard $jobs,
        FindBuyersCanaryRunner $runner,
        FindBuyersProgressQuery $progress,
        ExistingYandexSecretExposureScanner $scanner,
        SearchProviderRegistry $providers,
        YandexSearchService $yandexSearch,
    ): int {
        $live = (bool) $this->option('live');
        $explicitDryRun = (bool) $this->option('dry-run');
        $retainDatabase = (bool) $this->option('retain-db');
        $httpGuard = new FindBuyersCanaryHttpGuard;
        $databasePath = null;
        $apiKey = null;

        try {
            if ($live && $explicitDryRun) {
                throw new SearchProviderException('canary_policy', 'stage11b_canary_mode_conflict');
            }
            if ($live && ! (bool) $this->option('yes')) {
                throw new SearchProviderException('canary_policy', 'stage11b_explicit_confirmation_required');
            }
            $jobPublicId = (string) $this->argument('job');
            if (! Str::isUuid($jobPublicId)) {
                throw new SearchProviderException('canary_policy', 'stage11b_job_uuid_invalid');
            }

            $databasePath = $environment->assertEnvironmentAndDatabase();
            $environment->assertDefaultOffState();
            $worktreeState = $repository->assertExpectedWorktree();

            $apiKey = (string) config('services.yandex_search.api_key');
            $folderId = (string) config('services.yandex_search.folder_id');
            if ($apiKey === '' || $folderId === '') {
                throw new SearchProviderException('configuration', 'stage11b_yandex_not_configured');
            }
            if (! $yandexSearch->configuredHostIsAllowlisted()) {
                throw new SearchProviderException('configuration', 'stage11b_yandex_host_blocked');
            }
            if (! $providers->get('existing_yandex') instanceof ExistingYandexSearchProviderAdapter) {
                throw new SearchProviderException('configuration', 'stage11b_existing_yandex_adapter_required');
            }

            $job = ProspectingSearchJob::query()->where('public_id', $jobPublicId)->first();
            if (! $job) {
                throw new SearchProviderException('canary_policy', 'stage11b_job_not_found');
            }
            $environment->assertSyntheticDatabase($job);
            $context = $jobs->validate($job);
            $secretScan = $scanner->assertSecretAbsent($apiKey);
            $keySuffix = substr(hash_hmac(
                'sha256',
                $apiKey,
                'stage11b-find-buyers-readiness-v1',
            ), -12);

            $this->line($this->safeJson([
                'phase' => 'preflight',
                'mode' => $live ? 'live' : 'dry_run',
                'environment' => app()->environment(),
                'database_driver' => 'sqlite',
                'temp_database_path' => $databasePath,
                'default_mysql_selected' => false,
                'default_mysql_connected' => false,
                'config_cached' => false,
                'worktree_state' => $worktreeState,
                'job_public_id' => $context->job->public_id,
                'job_status' => $context->job->status->value,
                'product' => $context->productName,
                'launch_source_type' => $context->launchSourceType,
                'originating_good_count' => $context->originatingGoodCount,
                'product_mapping_state' => $context->job->product_mapping_state->value,
                'generated_query' => $context->query->safe_display_query,
                'yandex_key_configured' => true,
                'yandex_folder_configured' => true,
                'yandex_key_hmac_suffix' => $keySuffix,
                'yandex_host_profile' => 'allowlisted_existing_yandex',
                'secret_scan' => $secretScan,
                'browser_live_execute_allowed' => false,
                'caps' => [
                    ...$context->caps,
                    'total_live_http' => FindBuyersCanaryHttpGuard::MAX_TOTAL_REQUESTS,
                    'timeweb_requests' => 0,
                    'unit_changes' => 0,
                    'entity_changes' => 0,
                    'email' => 0,
                ],
            ]));

            if (! $live) {
                Http::preventStrayRequests();
            } else {
                $this->enableProcessLocalLiveFlags();
                Http::globalRequestMiddleware(fn ($request) => $httpGuard->authorize($request));
            }

            $result = $runner->run($context, $live, $httpGuard);
            $findings = $scanner->databaseFindings($apiKey);
            if (in_array(true, $findings, true)) {
                throw new SearchProviderException('security', 'stage11b_unsafe_persistence_detected');
            }

            $this->resetProcessLocalFlags();
            $projection = $this->progressProjection($context->job->fresh(), $context->operator, $progress);
            $this->resetProcessLocalFlags();

            $this->line($this->safeJson([
                'phase' => 'result',
                ...$result,
                ...$httpGuard->summary(),
                'progress' => $projection,
                'cost_status' => $live ? 'unknown_bounded_by_one_yandex_request' : 'zero_no_http',
                'persistence_findings' => $findings,
                'database_retained' => $retainDatabase,
                'final_state' => $this->finalState(),
            ]));

            return self::SUCCESS;
        } catch (SearchProviderException $exception) {
            $this->resetProcessLocalFlags();
            $this->blocked($exception->category, $exception->safeCode, $httpGuard);

            return self::FAILURE;
        } catch (AuthorizationException) {
            $this->resetProcessLocalFlags();
            $this->blocked('authorization', 'stage11b_operator_permission_blocked', $httpGuard);

            return self::FAILURE;
        } catch (ValidationException) {
            $this->resetProcessLocalFlags();
            $this->blocked('canary_policy', 'stage11b_job_revalidation_failed', $httpGuard);

            return self::FAILURE;
        } catch (Throwable) {
            $this->resetProcessLocalFlags();
            $this->blocked('internal', 'stage11b_canary_failed_safely', $httpGuard);

            return self::FAILURE;
        } finally {
            $this->resetProcessLocalFlags();
            if (! $retainDatabase && is_string($databasePath)) {
                $this->deleteIsolatedDatabase($databasePath);
            }
        }
    }

    private function enableProcessLocalLiveFlags(): void
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
            'ai-sales.find_buyers.ui_enabled' => false,
            'ai-sales.find_buyers.drafts_enabled' => false,
            'ai-sales.find_buyers.live_execution_enabled' => true,
            'ai-sales.find_buyers.auto_research_enabled' => false,
            'ai-sales.find_buyers.auto_scoring_enabled' => false,
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.prospecting.candidate_import_enabled' => true,
            'ai-sales.prospecting.auto_create_unit' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.prospecting.live_probe_enabled' => false,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.prospecting.scoring_enabled' => false,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.prospecting.ai_evidence_enabled' => false,
            'ai-sales.prospecting.live_scoring_enabled' => false,
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

    private function enableProgressProjectionFlags(): void
    {
        config()->set([
            'ai-sales.prospecting.dossier_enabled' => true,
            'ai-sales.prospecting.jobs_enabled' => true,
            'ai-sales.find_buyers.ui_enabled' => true,
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
            'ai-sales.find_buyers.ui_enabled' => false,
            'ai-sales.find_buyers.drafts_enabled' => false,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.find_buyers.auto_research_enabled' => false,
            'ai-sales.find_buyers.auto_scoring_enabled' => false,
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
            'ai-sales.prospecting.scoring_enabled' => false,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.prospecting.ai_evidence_enabled' => false,
            'ai-sales.prospecting.live_scoring_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function progressProjection(ProspectingSearchJob $job, $operator, FindBuyersProgressQuery $progress): array
    {
        $this->enableProgressProjectionFlags();
        $payload = $progress->get($job, $operator)->toArray();

        return [
            'stage' => $payload['stage'],
            'progress_percent' => $payload['progress_percent'],
            'counts' => $payload['counts'],
            'fetch_outcomes' => $payload['fetch_outcomes'],
        ];
    }

    private function blocked(string $category, string $code, FindBuyersCanaryHttpGuard $httpGuard): void
    {
        $this->line($this->safeJson([
            'phase' => 'blocked',
            'status' => $category === 'security' ? 'STOP_SECURITY' : 'blocked',
            'safe_category' => $category,
            'safe_code' => $code,
            ...$httpGuard->summary(),
            'raw_body_printed' => false,
            'secret_printed' => false,
            'final_state' => $this->finalState(),
        ]));
    }

    /** @return array<string, mixed> */
    private function finalState(): array
    {
        return [
            'ai_sales_enabled' => (bool) config('ai-sales.enabled', false),
            'transport_mode' => config('ai-sales.transport_mode'),
            'find_buyers_ui' => (bool) config('ai-sales.find_buyers.ui_enabled', false),
            'find_buyers_drafts' => (bool) config('ai-sales.find_buyers.drafts_enabled', false),
            'find_buyers_live_execution' => (bool) config('ai-sales.find_buyers.live_execution_enabled', false),
            'query_planning' => (bool) config('ai-sales.prospecting.query_planning_enabled', false),
            'search_execution' => (bool) config('ai-sales.prospecting.search_execution_enabled', false),
            'existing_yandex_provider' => (bool) config('ai-sales.prospecting.existing_yandex_provider_enabled', false),
            'page_fetch' => (bool) config('ai-sales.prospecting.page_fetch_enabled', false),
            'auto_candidate_ingestion' => (bool) config('ai-sales.prospecting.auto_candidate_ingestion_enabled', false),
            'public_research' => (bool) config('ai-sales.prospecting.public_research_enabled', false),
            'timeweb' => (bool) config('ai-sales.providers.timeweb.enabled', false),
            'auto_scoring' => (bool) config('ai-sales.prospecting.auto_scoring_enabled', false),
            'retries' => (int) config('ai-sales.limits.max_retries', 0),
            'failover' => (bool) config('ai-sales.provider_failover_enabled', false),
            'kill_switches' => 'blocking',
        ];
    }

    private function deleteIsolatedDatabase(string $path): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
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
