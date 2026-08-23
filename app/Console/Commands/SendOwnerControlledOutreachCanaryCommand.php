<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryEnvironmentGuard;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryRepositoryGuard;
use App\Domain\AiSales\Outreach\OwnerCanary\OwnerControlledCanaryRunner;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendOwnerControlledOutreachCanaryCommand extends Command
{
    public const OWNER_PHRASE = 'Stage 13B hardened callback environment and owner-controlled mailbox are confirmed. Send exactly one canary email.';

    public const PRODUCTION_PHRASE = 'I authorize one Stage 13B canary email in production to my controlled test mailbox.';

    protected $signature = 'ai-sales:send-owner-controlled-outreach-canary
        {--dry-run : Validate the fixed canary without provider delivery (default)}
        {--live : Permit the single owner-controlled provider delivery}
        {--yes : Explicitly confirm the one-message live operation}
        {--observe-seconds=0 : Observe normalized local events for 0..300 seconds without provider polling}';

    protected $description = 'Validate or execute the fixed one-message Stage 13B owner-controlled outreach canary';

    /** @var array<string, mixed> */
    private array $originalConfig = [];

    public function handle(
        OwnerControlledCanaryRepositoryGuard $repository,
        OwnerControlledCanaryEnvironmentGuard $environment,
        OwnerControlledCanaryRunner $runner,
    ): int {
        $live = (bool) $this->option('live');
        $explicitDryRun = (bool) $this->option('dry-run');
        $lock = null;
        $result = null;

        try {
            $observeSeconds = $this->observeSeconds();
            if ($live && $explicitDryRun) {
                throw new PolicyViolation('stage13b_mode_conflict', 'Choose either dry-run or live mode.');
            }
            if (($live && ! (bool) $this->option('yes')) || (! $live && (bool) $this->option('yes'))) {
                throw new PolicyViolation('stage13b_explicit_confirmation_required', 'The --yes confirmation is valid only with --live.');
            }
            if (! $live && $observeSeconds !== 0) {
                throw new PolicyViolation('stage13b_observation_requires_live', 'Event observation is available only after the single live attempt.');
            }

            $worktree = $repository->assertExpectedWorktree();
            $environment->assertDefaultOffState();
            $configuration = $environment->configuration($live);
            if ($live) {
                $this->assertOwnerAuthorization($configuration->environment);
            }
            $database = $environment->assertDatabaseAndDeployment($live);

            if ($live) {
                $lock = Cache::lock('ai-sales:stage13b:owner-controlled-canary', 900);
                if (! $lock->get()) {
                    throw new PolicyViolation('stage13b_canary_lock_unavailable', 'Another owner-controlled canary process is active.');
                }
            }

            $this->snapshotConfig();
            $this->enableProcessLocalFlags($live);
            Mail::fake();
            if (! $live) {
                Http::preventStrayRequests();
            }

            $this->line($this->safeJson([
                'phase' => 'preflight',
                'mode' => $live ? 'live' : 'dry_run',
                'environment' => $configuration->environment,
                'worktree_state' => $worktree,
                'database_connection' => $database['connection'],
                'database_driver' => $database['driver'],
                'database_name' => $database['database'],
                'security_migration' => $database['migration'],
                'security_evidence_reference' => $live ? $configuration->securityEvidenceReference : 'required_for_live_only',
                'security_evidence_sha256' => $live ? $configuration->securityEvidenceSha256 : null,
                'security_verified_at' => $live ? $configuration->securityVerifiedAt->toIso8601String() : null,
                'callback_host' => $configuration->callbackHost,
                'callback_path' => $configuration->callbackPath,
                'webhook_queue_connection' => $configuration->webhookQueueConnection,
                'webhook_event_format' => 'json_post',
                'recipient_configured' => true,
                'recipient_hmac_suffix' => $configuration->recipientHmacSuffix,
                'provider_key_configured' => true,
                'provider_key_hmac_suffix' => $configuration->providerKeyHmacSuffix,
                'permission_evidence_reference' => $configuration->permissionEvidenceReference,
                'permission_evidence_sha256' => $configuration->permissionEvidenceSha256,
                'scenario' => 'owner_controlled_broccoli_dispatch_v1',
                'caps' => $this->caps(),
                'browser_live_send_enabled' => false,
            ]));

            $result = $live
                ? $runner->live($configuration, $observeSeconds)
                : $runner->dryRun($configuration);

            $this->restoreConfig();
            $this->line($this->safeJson([
                'phase' => 'result',
                ...$result,
                'final_flags' => $this->finalFlags(),
                'recipient_printed' => false,
                'provider_key_printed' => false,
                'raw_provider_body_printed' => false,
            ]));

            return self::SUCCESS;
        } catch (PolicyViolation $exception) {
            $this->restoreConfig();
            $this->blocked($exception->errorCode, $result);

            return self::FAILURE;
        } catch (Throwable) {
            $this->restoreConfig();
            $this->blocked('stage13b_canary_failed_safely', $result);

            return self::FAILURE;
        } finally {
            $this->restoreConfig();
            if ($lock instanceof Lock) {
                $lock->release();
            }
        }
    }

    private function observeSeconds(): int
    {
        $value = (string) $this->option('observe-seconds');
        if (preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $value) !== 1) {
            throw new PolicyViolation('stage13b_observation_window_invalid', 'Observation must be an integer from 0 through 300.');
        }
        $seconds = (int) $value;
        if ($seconds > 300) {
            throw new PolicyViolation('stage13b_observation_window_invalid', 'Observation must be an integer from 0 through 300.');
        }

        return $seconds;
    }

    private function assertOwnerAuthorization(string $environment): void
    {
        $ownerPhrase = (string) $this->secret('Enter the exact Stage 13B owner authorization phrase');
        if (! hash_equals(self::OWNER_PHRASE, $ownerPhrase)) {
            throw new PolicyViolation('stage13b_owner_authorization_missing', 'The exact owner authorization phrase is required.');
        }
        $ownerPhrase = '';

        if ($environment === 'production') {
            $productionPhrase = (string) $this->secret('Enter the exact additional production authorization phrase');
            if (! hash_equals(self::PRODUCTION_PHRASE, $productionPhrase)) {
                throw new PolicyViolation('stage13b_production_authorization_missing', 'The exact additional production authorization phrase is required.');
            }
            $productionPhrase = '';
        }
    }

    private function snapshotConfig(): void
    {
        foreach ($this->mutableConfig() as $key => $_) {
            $this->originalConfig[$key] = config($key);
        }
    }

    private function enableProcessLocalFlags(bool $live): void
    {
        config()->set([
            'ai-sales.enabled' => true,
            'ai-sales.outreach_drafting_enabled' => true,
            'ai-sales.outreach_sending_enabled' => $live,
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => true,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => true,
            'ai-sales.outreach.suppression_management_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => $live,
            'ai-sales.outreach.dispatch_pipeline_enabled' => true,
            'ai-sales.outreach.queue_enabled' => $live,
            'ai-sales.outreach.provider_send_enabled' => $live,
            'ai-sales.outreach.event_ingestion_enabled' => $live,
            'ai-sales.outreach.reply_correlation_enabled' => false,
            'ai-sales.outreach.reply_triage_enabled' => false,
            'ai-sales.outreach.followup_planning_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.live_synthetic_canary_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.outreach.limits.global_daily_sends' => $live ? 1 : 0,
            'ai-sales.outreach.limits.per_domain_daily_sends' => $live ? 1 : 0,
            'ai-sales.outreach.limits.max_follow_ups' => 0,
            'ai-sales.outreach.limits.provider_retries' => 0,
            'ai-sales.outreach.limits.provider_failover' => false,
            'services.unisender_go.track_read' => false,
            'services.unisender_go.track_links' => false,
            'queue.default' => $live ? 'sync' : config('queue.default'),
        ]);
    }

    private function restoreConfig(): void
    {
        if ($this->originalConfig === []) {
            return;
        }

        config()->set($this->originalConfig);
        $this->originalConfig = [];
    }

    /** @return array<string, mixed> */
    private function mutableConfig(): array
    {
        return [
            'ai-sales.enabled' => false,
            'ai-sales.outreach_drafting_enabled' => false,
            'ai-sales.outreach_sending_enabled' => false,
            'ai-sales.outreach.ui_enabled' => false,
            'ai-sales.outreach.drafts_enabled' => false,
            'ai-sales.outreach.fake_generation_enabled' => false,
            'ai-sales.outreach.permission_ledger_enabled' => false,
            'ai-sales.outreach.suppression_management_enabled' => false,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.dispatch_pipeline_enabled' => false,
            'ai-sales.outreach.queue_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.event_ingestion_enabled' => false,
            'ai-sales.outreach.reply_correlation_enabled' => false,
            'ai-sales.outreach.reply_triage_enabled' => false,
            'ai-sales.outreach.followup_planning_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.live_generation_enabled' => false,
            'ai-sales.outreach.live_synthetic_canary_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.outreach.transport_mode' => 'fake_only',
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.local_ru_calls_enabled' => false,
            'ai-sales.external_sanitized_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.autonomous_campaigns_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.live_business_workflows_enabled' => false,
            'ai-sales.outreach.limits.global_daily_sends' => 0,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 0,
            'ai-sales.outreach.limits.max_follow_ups' => 0,
            'ai-sales.outreach.limits.provider_retries' => 0,
            'ai-sales.outreach.limits.provider_failover' => false,
            'services.unisender_go.track_read' => false,
            'services.unisender_go.track_links' => false,
            'queue.default' => null,
        ];
    }

    /** @return array<string, int> */
    private function caps(): array
    {
        return [
            'provider_send_requests' => 1,
            'recipients' => 1,
            'cc' => 0,
            'bcc' => 0,
            'attachments' => 0,
            'transport_retries' => 0,
            'queue_tries' => 1,
            'failovers' => 0,
            'follow_ups' => 0,
            'auto_replies' => 0,
            'timeweb_requests' => 0,
            'yandex_requests' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function finalFlags(): array
    {
        return [
            'ai_sales_enabled' => (bool) config('ai-sales.enabled', false),
            'outreach_dispatch' => (bool) config('ai-sales.outreach.dispatch_enabled', false),
            'outreach_queue' => (bool) config('ai-sales.outreach.queue_enabled', false),
            'outreach_provider_send' => (bool) config('ai-sales.outreach.provider_send_enabled', false),
            'outreach_event_ingestion' => (bool) config('ai-sales.outreach.event_ingestion_enabled', false),
            'outreach_auto_send' => (bool) config('ai-sales.outreach.auto_send_enabled', false),
            'outreach_auto_followup' => (bool) config('ai-sales.outreach.auto_followup_enabled', false),
            'transport_mode' => config('ai-sales.transport_mode'),
            'provider_retries' => (int) config('ai-sales.outreach.limits.provider_retries', 0),
            'provider_failover' => (bool) config('ai-sales.outreach.limits.provider_failover', false),
            'max_follow_ups' => (int) config('ai-sales.outreach.limits.max_follow_ups', 0),
        ];
    }

    /** @param array<string, mixed>|null $result */
    private function blocked(string $safeCode, ?array $result): void
    {
        $this->line($this->safeJson([
            'phase' => 'blocked',
            'status' => 'blocked',
            'safe_code' => $safeCode,
            'provider_send_requests' => (int) ($result['provider_send_requests'] ?? 0),
            'emails_addressed' => (int) ($result['emails_addressed'] ?? 0),
            'recipient_printed' => false,
            'provider_key_printed' => false,
            'raw_provider_body_printed' => false,
            'final_flags' => $this->finalFlags(),
        ]));
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
