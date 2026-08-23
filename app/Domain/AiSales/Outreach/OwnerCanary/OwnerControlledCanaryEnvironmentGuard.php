<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderRequestProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OwnerControlledCanaryEnvironmentGuard
{
    public const SECURITY_MIGRATION = '2026_08_17_123000_harden_unisender_provider_persistence';

    private const MAX_SECURITY_EVIDENCE_AGE_HOURS = 24;

    public function __construct(private readonly UnisenderGoClient $unisender) {}

    public function assertDefaultOffState(): void
    {
        foreach ([
            'enabled',
            'external_calls_enabled',
            'local_ru_calls_enabled',
            'external_sanitized_calls_enabled',
            'provider_failover_enabled',
            'web_search_enabled',
            'outreach_drafting_enabled',
            'outreach_sending_enabled',
            'autonomous_campaigns_enabled',
            'provider_native_tools_enabled',
            'live_business_workflows_enabled',
            'outreach.ui_enabled',
            'outreach.drafts_enabled',
            'outreach.fake_generation_enabled',
            'outreach.permission_ledger_enabled',
            'outreach.suppression_management_enabled',
            'outreach.dispatch_enabled',
            'outreach.dispatch_pipeline_enabled',
            'outreach.queue_enabled',
            'outreach.provider_send_enabled',
            'outreach.event_ingestion_enabled',
            'outreach.reply_correlation_enabled',
            'outreach.reply_triage_enabled',
            'outreach.followup_planning_enabled',
            'outreach.auto_followup_enabled',
            'outreach.live_generation_enabled',
            'outreach.live_synthetic_canary_enabled',
            'outreach.auto_send_enabled',
        ] as $flag) {
            if ((bool) config('ai-sales.'.$flag, false)) {
                throw new PolicyViolation('stage13b_runtime_not_default_off', 'Ordinary outreach and AI runtime must be default-off before the canary.');
            }
        }

        if (config('ai-sales.transport_mode', 'fake_only') !== 'fake_only'
            || config('ai-sales.outreach.transport_mode', 'fake_only') !== 'fake_only'
            || (int) config('ai-sales.outreach.limits.provider_retries', 0) !== 0
            || (bool) config('ai-sales.outreach.limits.provider_failover', false)
            || (int) config('ai-sales.outreach.limits.max_follow_ups', 0) !== 0) {
            throw new PolicyViolation('stage13b_runtime_not_default_off', 'Retry, failover, follow-up and transport guards are not in their default-off state.');
        }
    }

    public function configuration(bool $live): OwnerControlledCanaryConfiguration
    {
        $environment = mb_strtolower(trim((string) config('ai-sales.outreach.owner_canary.environment')));
        $actualEnvironment = mb_strtolower(app()->environment());
        $allowed = $live ? ['staging', 'production'] : ['local', 'testing', 'staging'];
        if (! in_array($environment, $allowed, true) || ! hash_equals($environment, $actualEnvironment)) {
            throw new PolicyViolation('stage13b_environment_not_approved', 'The exact canary environment is absent or does not match APP_ENV.');
        }

        $recipient = trim((string) config('ai-sales.outreach.owner_canary.recipient'));
        if (! $this->validEmail($recipient)) {
            throw new PolicyViolation('stage13b_recipient_missing_or_invalid', 'The protected owner-controlled recipient is absent or invalid.');
        }

        $appKey = (string) config('app.key');
        $providerKey = trim((string) config('services.unisender_go.api_key'));
        if ($appKey === '' || $providerKey === '') {
            throw new PolicyViolation('stage13b_provider_secret_missing', 'The application HMAC key or Unisender credential is not configured.');
        }
        if (! (bool) config('services.unisender_go.enabled', false)) {
            throw new PolicyViolation('stage13b_unisender_disabled', 'The existing Unisender provider is not configured as enabled.');
        }

        $from = trim((string) config('services.unisender_go.from_email'));
        $replyTo = trim((string) config('services.unisender_go.reply_to'));
        if (! $this->validEmail($from) || ! $this->validEmail($replyTo)) {
            throw new PolicyViolation('stage13b_sender_configuration_invalid', 'Server-owned From or Reply-To is invalid.');
        }

        $providerUrl = $this->validatedUrl(
            (string) config('services.unisender_go.api_base'),
            'stage13b_provider_url_invalid',
        );
        if (preg_match('/\Ago[0-9]+\.unisender\.ru\z/D', $providerUrl['host']) !== 1
            || $providerUrl['path'] !== '/en/transactional/api/v1') {
            throw new PolicyViolation('stage13b_provider_url_invalid', 'The Unisender API target is outside the audited host/path contract.');
        }

        $callback = $this->validatedUrl(
            (string) config('services.unisender_go.webhook_url'),
            'stage13b_callback_url_invalid',
        );
        $appUrl = $this->validatedUrl((string) config('app.url'), 'stage13b_application_url_invalid');
        if ($callback['path'] !== '/webhooks/unisender-go'
            || ! hash_equals($appUrl['host'], $callback['host'])) {
            throw new PolicyViolation('stage13b_callback_environment_mismatch', 'The callback does not point to the selected application environment.');
        }

        $queueConnection = trim((string) config('services.unisender_go.webhook_queue_connection'));
        if ($queueConnection === '' || $queueConnection === 'sync' || ! is_array(config('queue.connections.'.$queueConnection))) {
            throw new PolicyViolation('stage13b_webhook_queue_not_async', 'The hardened callback requires a configured non-sync queue.');
        }
        if ($live && (string) config('queue.default') === 'sync') {
            throw new PolicyViolation('stage13b_dispatch_queue_not_async', 'The deployed dispatch worker must use a non-sync queue before the process-local canary run.');
        }
        if (($this->unisender->defaultWebhookConfig()['event_format'] ?? null) !== 'json_post') {
            throw new PolicyViolation('stage13b_webhook_format_invalid', 'Only the audited json_post webhook format is allowed.');
        }
        if (UnisenderRequestProfile::OutreachZeroRetry->transportRetries() !== 0
            || UnisenderRequestProfile::OutreachZeroRetry->queueTries() !== 1) {
            throw new PolicyViolation('stage13b_zero_retry_profile_invalid', 'The outreach zero-retry profile is not exact.');
        }
        if (! Route::has('mailings.unsubscribe.show') || ! Route::has('mailings.unsubscribe.submit')) {
            throw new PolicyViolation('stage13b_unsubscribe_routes_missing', 'Code-owned unsubscribe routes are required.');
        }

        [$permissionReference, $permissionHash] = $this->evidencePair(
            'permission_evidence_reference',
            'permission_evidence_sha256',
            'stage13b_permission_evidence_missing',
        );

        $securityReference = '';
        $securityHash = '';
        $securityVerifiedAt = CarbonImmutable::now();
        if ($live) {
            [$securityReference, $securityHash] = $this->evidencePair(
                'security_evidence_reference',
                'security_evidence_sha256',
                'stage13b_security_evidence_missing',
            );
            $securityVerifiedAt = $this->securityVerifiedAt();
        }

        return new OwnerControlledCanaryConfiguration(
            environment: $environment,
            recipient: $recipient,
            recipientHmacSuffix: $this->hmacSuffix($recipient, $appKey),
            providerKeyHmacSuffix: $this->hmacSuffix($providerKey, $appKey),
            permissionEvidenceReference: $permissionReference,
            permissionEvidenceSha256: $permissionHash,
            securityEvidenceReference: $securityReference,
            securityEvidenceSha256: $securityHash,
            securityVerifiedAt: $securityVerifiedAt,
            callbackHost: $callback['host'],
            callbackPath: $callback['path'],
            providerHost: $providerUrl['host'],
            webhookQueueConnection: $queueConnection,
        );
    }

    /** @return array{connection: string, driver: string, database: string, migration: string} */
    public function assertDatabaseAndDeployment(bool $live): array
    {
        $connectionName = (string) config('database.default');
        if (! $live && $connectionName !== 'sqlite') {
            throw new PolicyViolation('stage13b_isolated_sqlite_required', 'Dry-run requires isolated SQLite and never uses default MySQL.');
        }

        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();
        if (! $live && ($driver !== 'sqlite' || ($database !== ':memory:' && ! $this->isOsTempPath($database)))) {
            throw new PolicyViolation('stage13b_isolated_sqlite_required', 'Dry-run requires in-memory or OS-temp SQLite.');
        }
        if ($live && $driver === 'sqlite' && $database === ':memory:') {
            throw new PolicyViolation('stage13b_durable_database_required', 'Live canary evidence requires a durable staging or approved production database.');
        }

        $schema = Schema::connection($connectionName);
        if (! $schema->hasTable('migrations')
            || ! DB::connection($connectionName)->table('migrations')->where('migration', self::SECURITY_MIGRATION)->exists()) {
            throw new PolicyViolation('stage13b_security_migration_missing', 'The hardened provider persistence migration is not applied.');
        }
        foreach ([
            'mailing_webhook_calls' => ['request_hash', 'verified_at', 'processed_at', 'safe_error_code', 'safe_summary'],
            'mailing_events' => ['event_fingerprint', 'normalized_event_type', 'normalized_status', 'verified_at', 'processed_at'],
            'mailing_messages' => ['request_hash', 'response_hash', 'request_profile', 'safe_error_code', 'safe_summary'],
        ] as $table => $columns) {
            if (! $schema->hasColumns($table, $columns)) {
                throw new PolicyViolation('stage13b_normalized_schema_missing', 'The normalized hardened provider schema is incomplete.');
            }
        }

        return [
            'connection' => $connectionName,
            'driver' => $driver,
            'database' => $database === ':memory:' ? ':memory:' : basename($database),
            'migration' => self::SECURITY_MIGRATION,
        ];
    }

    private function validEmail(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 254
            && preg_match('/[\r\n]/', $value) !== 1
            && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array{host: string, path: string} */
    private function validatedUrl(string $value, string $errorCode): array
    {
        $parts = parse_url(trim($value));
        if (! is_array($parts)
            || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! is_string($parts['host'] ?? null)
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new PolicyViolation($errorCode, 'A required HTTPS endpoint is invalid.');
        }

        return [
            'host' => mb_strtolower($parts['host']),
            'path' => rtrim((string) ($parts['path'] ?? ''), '/'),
        ];
    }

    /** @return array{string, string} */
    private function evidencePair(string $referenceKey, string $hashKey, string $errorCode): array
    {
        $reference = trim((string) config('ai-sales.outreach.owner_canary.'.$referenceKey));
        $hash = mb_strtolower(trim((string) config('ai-sales.outreach.owner_canary.'.$hashKey)));
        if ($reference === '' || mb_strlen($reference) > 512
            || preg_match('/\A[a-z0-9][a-z0-9:._\/-]+\z/iD', $reference) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1) {
            throw new PolicyViolation($errorCode, 'A safe evidence reference and SHA-256 are required.');
        }

        return [$reference, $hash];
    }

    private function securityVerifiedAt(): CarbonImmutable
    {
        try {
            $verified = CarbonImmutable::parse((string) config('ai-sales.outreach.owner_canary.security_verified_at'));
        } catch (Throwable) {
            throw new PolicyViolation('stage13b_security_evidence_stale', 'Security deployment evidence has no valid timestamp.');
        }

        $now = CarbonImmutable::now();
        if ($verified->isAfter($now->addMinute()) || $verified->isBefore($now->subHours(self::MAX_SECURITY_EVIDENCE_AGE_HOURS))) {
            throw new PolicyViolation('stage13b_security_evidence_stale', 'Security deployment evidence is stale or future-dated.');
        }

        return $verified;
    }

    private function hmacSuffix(string $value, string $key): string
    {
        return substr(hash_hmac('sha256', mb_strtolower(trim($value)), $key), -12);
    }

    private function isOsTempPath(string $path): bool
    {
        $directory = realpath(dirname($path)) ?: dirname($path);
        $temp = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();

        return str_starts_with($directory.DIRECTORY_SEPARATOR, rtrim($temp, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
