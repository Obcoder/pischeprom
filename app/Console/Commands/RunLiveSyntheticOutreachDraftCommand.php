<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryContract;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryEnvironmentGuard;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryEvidenceService;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryRepositoryGuard;
use App\Domain\AiSales\Outreach\Canary\OutreachCanaryRunner;
use App\Infrastructure\AiSales\Timeweb\TimewebAiGatewayConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class RunLiveSyntheticOutreachDraftCommand extends Command
{
    protected $signature = 'ai-sales:run-live-synthetic-outreach-draft
        {--dry-run : Validate the fixed canary locally without HTTP (default)}
        {--live : Permit exactly one bounded Timeweb Responses request}
        {--yes : Explicitly confirm the single live request}
        {--retain-db : Retain the isolated synthetic SQLite file for approved debugging}';

    protected $description = 'Run the fixed testing-only Stage 12B synthetic outreach draft canary';

    public function handle(
        OutreachCanaryEnvironmentGuard $environment,
        OutreachCanaryRepositoryGuard $repository,
        OutreachCanaryEvidenceService $evidence,
        OutreachCanaryRunner $runner,
        TimewebAiGatewayConfiguration $timeweb,
    ): int {
        $live = (bool) $this->option('live');
        $explicitDryRun = (bool) $this->option('dry-run');
        $retainDatabase = (bool) $this->option('retain-db');
        $databasePath = null;
        $apiKey = null;

        try {
            if ($live && $explicitDryRun) {
                throw new PolicyViolation('stage12b_canary_mode_conflict', 'Choose either dry-run or live mode.');
            }
            if (($live && ! (bool) $this->option('yes')) || (! $live && (bool) $this->option('yes'))) {
                throw new PolicyViolation('stage12b_explicit_confirmation_required', 'The --yes confirmation is valid only with --live.');
            }

            $databasePath = $environment->assertEnvironmentAndDatabase();
            $environment->assertDefaultOffState();
            $worktreeState = $repository->assertExpectedWorktree();
            $environment->assertPristineSyntheticDatabase();

            $keySuffix = $timeweb->fingerprint(AiProviderRoute::ExternalSanitized);
            if ($keySuffix === null) {
                throw new PolicyViolation('stage12b_external_key_missing', 'The external Timeweb staging key is not configured.');
            }
            $apiKey = $timeweb->apiKey(AiProviderRoute::ExternalSanitized);

            $this->enableProcessLocalCanaryFlags();
            $evidence->installAcceptedEphemeralEvidence();
            $evidenceState = $evidence->assertReady();
            $effectiveLimits = $timeweb->probeLimits();

            Mail::fake();
            Queue::fake();
            if (! $live) {
                Http::preventStrayRequests();
            }

            $this->line($this->safeJson([
                'phase' => 'preflight',
                'mode' => $live ? 'live' : 'dry_run',
                'environment' => app()->environment(),
                'database_driver' => 'sqlite',
                'temp_database_path' => $databasePath,
                'database_permissions' => '0600',
                'default_mysql_selected' => false,
                'default_mysql_connected' => false,
                'config_cached' => false,
                'worktree_state' => $worktreeState,
                'external_key_configured' => true,
                'external_key_hmac_suffix' => $keySuffix,
                'scenario' => OutreachCanaryContract::SCENARIO,
                'route' => 'external_sanitized',
                'model' => OutreachCanaryContract::MODEL_ID,
                'evidence' => $evidenceState,
                'caps' => [
                    'timeweb_requests' => 1,
                    'input_tokens' => $effectiveLimits['max_input_tokens'],
                    'output_tokens' => $effectiveLimits['max_output_tokens'],
                    'estimated_rub' => $effectiveLimits['max_rub'],
                    'wall_clock_seconds' => $effectiveLimits['max_wall_clock_seconds'],
                    'retries' => 0,
                    'failovers' => 0,
                    'yandex_requests' => 0,
                    'other_http' => 0,
                    'emails' => 0,
                    'mail_jobs' => 0,
                ],
                'ordinary_unit_ai_runtime' => false,
                'manual_mail_routes_invoked' => false,
                'dispatch_enabled' => false,
            ]));

            $result = $runner->run($live, $apiKey);
            Mail::assertNothingSent();
            Queue::assertNothingPushed();

            $this->resetProcessLocalFlags();
            $this->line($this->safeJson([
                'phase' => 'result',
                ...$result,
                'mail_sent' => 0,
                'mail_jobs' => 0,
                'unisender_calls' => 0,
                'manual_mail_service_calls' => 0,
                'tracking_calls' => 0,
                'database_retained' => $retainDatabase,
                'final_state' => $this->finalState(),
            ]));

            return self::SUCCESS;
        } catch (PolicyViolation $exception) {
            $this->resetProcessLocalFlags();
            $this->blocked($exception->errorCode, $runner->httpSummary());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->resetProcessLocalFlags();
            $this->blocked('stage12b_canary_failed_safely', $runner->httpSummary());

            return self::FAILURE;
        } finally {
            $this->resetProcessLocalFlags();
            $apiKey = null;
            if (! $retainDatabase && is_string($databasePath)) {
                $this->deleteIsolatedDatabase($databasePath);
            }
        }
    }

    private function enableProcessLocalCanaryFlags(): void
    {
        $configuredMaxRub = (float) config('ai-sales.providers.timeweb.probe.max_rub', 0);
        $configuredInput = (int) config('ai-sales.providers.timeweb.probe.max_input_tokens', 0);
        $configuredOutput = (int) config('ai-sales.providers.timeweb.probe.max_output_tokens', 0);
        $configuredWallClock = (int) config('ai-sales.providers.timeweb.probe.max_wall_clock_seconds', 0);

        config()->set([
            'cache.default' => 'array',
            'ai-sales.enabled' => true,
            'ai-sales.external_calls_enabled' => true,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => true,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => true,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => false,
            'ai-sales.outreach.suppression_management_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => true,
            'ai-sales.outreach.live_synthetic_canary_enabled' => true,
            'ai-sales.outreach.live_synthetic_canary_model_id' => OutreachCanaryContract::MODEL_ID,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'timeweb_synthetic_only',
            'ai-sales.transport_mode' => 'timeweb_synthetic_only',
            'ai-sales.providers.timeweb.enabled' => true,
            'ai-sales.providers.timeweb.routes.local_ru.enabled' => false,
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled' => true,
            'ai-sales.providers.timeweb.probe.enabled' => true,
            'ai-sales.providers.timeweb.probe.synthetic_only' => true,
            'ai-sales.providers.timeweb.probe.max_rub' => number_format(
                $configuredMaxRub > 0 ? min($configuredMaxRub, (float) OutreachCanaryContract::MAX_RUB) : (float) OutreachCanaryContract::MAX_RUB,
                4,
                '.',
                '',
            ),
            'ai-sales.providers.timeweb.probe.max_input_tokens' => $configuredInput > 0
                ? min($configuredInput, OutreachCanaryContract::MAX_INPUT_TOKENS)
                : OutreachCanaryContract::MAX_INPUT_TOKENS,
            'ai-sales.providers.timeweb.probe.max_output_tokens' => $configuredOutput > 0
                ? min($configuredOutput, OutreachCanaryContract::MAX_OUTPUT_TOKENS)
                : OutreachCanaryContract::MAX_OUTPUT_TOKENS,
            'ai-sales.providers.timeweb.probe.max_requests' => 1,
            'ai-sales.providers.timeweb.probe.max_wall_clock_seconds' => $configuredWallClock > 0
                ? min($configuredWallClock, 120)
                : 120,
            'ai-sales.providers.timeweb.probe.pricing_snapshot_version' => OutreachCanaryEvidenceService::PRICING_VERSION,
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
            'ai-sales.outreach_drafting_enabled' => false,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => false,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => false,
            'ai-sales.outreach.suppression_management_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.live_synthetic_canary_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.providers.timeweb.enabled' => false,
            'ai-sales.providers.timeweb.routes.local_ru.enabled' => false,
            'ai-sales.providers.timeweb.routes.external_sanitized.enabled' => false,
            'ai-sales.providers.timeweb.probe.enabled' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.prospecting.live_probe_enabled' => false,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function finalState(): array
    {
        return [
            'ai_sales_enabled' => (bool) config('ai-sales.enabled', false),
            'transport_mode' => config('ai-sales.transport_mode'),
            'outreach_drafting' => (bool) config('ai-sales.outreach_drafting_enabled', false),
            'outreach_live_generation' => (bool) config('ai-sales.outreach.live_generation_enabled', false),
            'outreach_live_synthetic_canary' => (bool) config('ai-sales.outreach.live_synthetic_canary_enabled', false),
            'outreach_dispatch' => (bool) config('ai-sales.outreach.dispatch_enabled', false),
            'outreach_sending' => (bool) config('ai-sales.outreach_sending_enabled', false),
            'outreach_auto_send' => (bool) config('ai-sales.outreach.auto_send_enabled', false),
            'timeweb' => (bool) config('ai-sales.providers.timeweb.enabled', false),
            'external_route' => (bool) config('ai-sales.providers.timeweb.routes.external_sanitized.enabled', false),
            'probe' => (bool) config('ai-sales.providers.timeweb.probe.enabled', false),
            'retries' => (int) config('ai-sales.limits.max_retries', 0),
            'failovers' => (bool) config('ai-sales.provider_failover_enabled', false),
            'kill_switches' => 'blocking_via_default_off_runtime',
        ];
    }

    /** @param array<string, int> $httpSummary */
    private function blocked(string $code, array $httpSummary): void
    {
        $this->line($this->safeJson([
            'phase' => 'blocked',
            'status' => 'blocked',
            'safe_code' => $code,
            ...$httpSummary,
            'raw_body_printed' => false,
            'secret_printed' => false,
            'recipient_printed' => false,
            'mail_sent' => 0,
            'mail_jobs' => 0,
            'final_state' => $this->finalState(),
        ]));
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
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        );
    }
}
