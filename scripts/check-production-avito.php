<?php

declare(strict_types=1);

namespace AvitoProductionCheck;

use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use App\Services\Avito\AutoReply\AvitoAutoReplyDiagnostics;
use App\Services\Avito\AvitoTokenManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

// Business data is read only. The provider request lists subscriptions; token
// renewal may update authentication bookkeeping. Never dispatch or retry jobs.
function inspect(callable $read): array
{
    try {
        return $read() + ['inspection' => 'complete'];
    } catch (Throwable) {
        return ['inspection' => 'unavailable'];
    }
}

function code(mixed $value): string
{
    return is_string($value) && preg_match('/^[a-z][a-z0-9_:-]{0,79}$/D', $value) === 1 ? $value : 'unknown';
}

/** Exclude human-readable diagnostics, which can contain account names. */
function safeDiagnostics(array $audit): array
{
    $result = [];
    foreach (['ready_for_sending', 'classifier_configured', 'avito_enabled', 'mutations_enabled', 'client_credentials_configured', 'webhook_secret_configured'] as $key) {
        $result[$key] = ($audit[$key] ?? false) === true;
    }
    $result['mode'] = code($audit['mode'] ?? null);
    $result['response_mode'] = code($audit['response_mode'] ?? null);
    $result['emergency_stopped'] = ! empty($audit['emergency_stopped_at']);
    foreach (['blockers', 'warnings'] as $key) {
        $result[$key] = array_values(array_unique(array_map(static fn ($row) => code($row['code'] ?? null), $audit[$key] ?? [])));
    }
    $result['rule_counts'] = [];
    foreach (['total', 'active_approved', 'in_scope', 'pilot_in_scope', 'eligible_before_safety', 'unsafe_responses', 'eligible'] as $key) {
        if (isset($audit['rules'][$key])) {
            $result['rule_counts'][$key] = (int) $audit['rules'][$key];
        }
    }
    $result['accounts'] = array_map(static fn ($row) => [
        'id' => (int) $row['id'],
        'sync_enabled' => (bool) $row['sync_enabled'],
        'sync_status' => code($row['sync_status'] ?? null),
        'credentials_ready' => (bool) $row['credentials_ready'],
        'write_scope_present' => $row['write_scope_present'] === null ? null : (bool) $row['write_scope_present'],
    ], $audit['accounts'] ?? []);

    return $result;
}

function subscriptionSummary(array $items, string $expectedUrl, string $secret): array
{
    $normalizeHost = static function (string $host): string {
        return strtolower(function_exists('idn_to_ascii') ? (idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host) : $host);
    };
    $expected = parse_url($expectedUrl);
    $result = ['subscription_count' => count($items), 'matching_endpoint_count' => 0, 'matching_current_secret_count' => 0];
    foreach ($items as $item) {
        $url = is_array($item) ? ($item['url'] ?? '') : $item;
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_array($parts) || ! is_array($expected)) {
            continue;
        }
        $same = ($parts['scheme'] ?? '') === 'https'
            && ($parts['port'] ?? 443) === 443
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && $normalizeHost($parts['host'] ?? '') === $normalizeHost($expected['host'] ?? '')
            && rtrim($parts['path'] ?? '', '/') === rtrim($expected['path'] ?? '', '/');
        if (! $same) {
            continue;
        }
        $result['matching_endpoint_count']++;
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        if ($secret !== '' && is_string($query['secret'] ?? null) && hash_equals($secret, $query['secret'])) {
            $result['matching_current_secret_count']++;
        }
    }

    return $result;
}

/** Parse arguments in memory; no command line, unknown option or path is emitted. */
function workerSummary(array $arguments, string $defaultConnection, array $queueDefaults): ?array
{
    $index = array_search('queue:work', $arguments, true);
    if ($index === false || $index === 0 || basename($arguments[$index - 1]) !== 'artisan') {
        return null;
    }
    $connection = $defaultConnection;
    $queueOption = null;
    $timeout = 60;
    for ($i = $index + 1; $i < count($arguments); $i++) {
        $arg = $arguments[$i];
        if ($i === $index + 1 && ! str_starts_with($arg, '-')) {
            $connection = $arg;
        } elseif (str_starts_with($arg, '--queue=')) {
            $queueOption = substr($arg, 8);
        } elseif ($arg === '--queue') {
            $queueOption = $arguments[++$i] ?? '';
        } elseif (str_starts_with($arg, '--timeout=')) {
            $timeout = (int) substr($arg, 10);
        } elseif ($arg === '--timeout') {
            $timeout = (int) ($arguments[++$i] ?? 0);
        }
    }
    $known = array_key_exists($connection, $queueDefaults);
    $queues = $queueOption === null ? [$queueDefaults[$connection] ?? 'unknown'] : explode(',', $queueOption);

    return [
        'connection' => $known ? code($connection) : 'unknown',
        'queues' => array_values(array_unique(array_map(__NAMESPACE__.'\\code', $queues))),
        'worker_default_timeout_seconds' => max(0, $timeout),
        'consumes_avito_queue' => $known && $connection === $defaultConnection && in_array($queueDefaults[$defaultConnection] ?? 'default', $queues, true),
        'consumes_realtime_queue' => $connection === 'database' && in_array('realtime', $queues, true),
    ];
}

function workers(string $targetDir): array
{
    $defaults = [];
    foreach ((array) config('queue.connections') as $name => $values) {
        $defaults[$name] = $values['queue'] ?? 'default';
    }
    $processes = [];
    $unreadable = 0;
    $paths = glob('/proc/[0-9]*/cmdline');
    if ($paths === false || $paths === []) {
        return ['inspection' => 'unavailable', 'default_worker_verified' => false];
    }
    foreach (array_slice($paths, 0, 10000) as $path) {
        $raw = @file_get_contents($path, false, null, 0, 16384);
        if ($raw === false) {
            $unreadable++;

            continue;
        }
        $summary = workerSummary(explode("\0", rtrim($raw, "\0")), (string) config('queue.default'), $defaults);
        if ($summary === null) {
            continue;
        }
        $cwd = @readlink(dirname($path).'/cwd');
        if ($cwd === false) {
            $unreadable++;

            continue;
        }
        if (rtrim($cwd, '/') === $targetDir) {
            $processes[] = ['pid' => (int) basename(dirname($path))] + $summary;
        }
    }

    return [
        'inspection' => $unreadable === 0 && count($paths) <= 10000 ? 'complete' : 'partial',
        'default_worker_verified' => in_array(true, array_column($processes, 'consumes_avito_queue'), true),
        'realtime_worker_verified' => in_array(true, array_column($processes, 'consumes_realtime_queue'), true),
        'processes' => $processes,
    ];
}

function serviceStatuses(): array
{
    $units = ['pischeprom-reverb.service', 'pischeprom-realtime-worker.service', 'pischeprom-worker.service', 'pischeprom-queue-worker.service', 'pischeprom-default-worker.service', 'pischeprom-mail-sync-worker.service', 'cron.service', 'crond.service'];
    $process = new Process(['systemctl', 'show', '--no-pager', '--property=Id,LoadState,ActiveState,SubState,MainPID', ...$units]);
    $process->setTimeout(5);
    $process->run();
    $result = [];
    foreach (preg_split('/\n\s*\n/', trim($process->getOutput())) ?: [] as $block) {
        $fields = [];
        foreach (explode("\n", $block) as $line) {
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            if (in_array($key, ['Id', 'LoadState', 'ActiveState', 'SubState', 'MainPID'], true)) {
                $fields[$key] = $value;
            }
        }
        if (in_array($fields['Id'] ?? '', $units, true)) {
            $result[$fields['Id']] = [
                'load_state' => code($fields['LoadState'] ?? null),
                'active_state' => code($fields['ActiveState'] ?? null),
                'sub_state' => code($fields['SubState'] ?? null),
                'pid' => (int) ($fields['MainPID'] ?? 0),
            ];
        }
    }

    return ['services' => $result, 'all_units_returned' => count($result) === count($units)];
}

function queueMetadata(string $connection, string $name): array
{
    $config = (array) config('queue.connections.'.$connection, []);
    $result = ['connection' => code($connection), 'queue' => code($name), 'driver' => code($config['driver'] ?? null), 'retry_after_seconds' => isset($config['retry_after']) ? (int) $config['retry_after'] : null];
    if (! in_array($config['driver'] ?? '', ['database', 'redis'], true)) {
        return $result + ['counts_inspection' => 'unsupported'];
    }
    $queue = Queue::connection($connection);
    $oldest = $queue->creationTimeOfOldestPendingJob($name);
    $result += [
        'counts_inspection' => 'complete',
        'total' => $queue->size($name),
        'pending' => $queue->pendingSize($name),
        'delayed' => $queue->delayedSize($name),
        'reserved' => $queue->reservedSize($name),
        'oldest_pending_created_at' => is_numeric($oldest) ? gmdate(DATE_ATOM, (int) $oldest) : null,
        'oldest_pending_age_seconds' => is_numeric($oldest) ? max(0, time() - (int) $oldest) : null,
    ];

    return $result;
}

function schemaMetadata(): array
{
    $required = ['2026_09_12_160000_expand_avito_auto_replies', '2026_09_12_170000_add_avito_auto_reply_emergency_stop', '2026_09_21_110000_add_history_sync_to_avito_chats'];
    $migrations = [];
    foreach ($required as $name) {
        $migrations[$name] = DB::table('migrations')->where('migration', $name)->exists();
    }
    $columns = [];
    foreach (['avito_auto_reply_settings' => ['response_mode', 'emergency_stopped_at'], 'avito_auto_reply_decisions' => ['response_text', 'matched_rule_keys'], 'avito_chats' => ['history_synced_at']] as $table => $names) {
        foreach ($names as $name) {
            $columns[$table.'.'.$name] = Schema::hasColumn($table, $name);
        }
    }

    return ['migrations' => $migrations, 'columns' => $columns, 'ready' => ! in_array(false, [...$migrations, ...$columns], true)];
}

function activityMetadata(): array
{
    $since = now()->subDay();
    $sync = DB::table('avito_messenger_sync_runs');
    $webhooks = DB::table('avito_webhook_events');
    $statuses = static fn ($query) => $query->selectRaw('status, count(*) as total')->groupBy('status')->get()->map(static fn ($row) => ['status' => code($row->status), 'count' => (int) $row->total])->all();

    return [
        'scheduled_sync_enabled' => (bool) config('avito.enabled'),
        'scheduled_sync_interval_minutes' => (int) config('avito.messenger.sync_interval_minutes'),
        'scheduler_process_verified' => false,
        'last_sync_started_at' => (clone $sync)->max('started_at'),
        'last_sync_finished_at' => (clone $sync)->max('finished_at'),
        'last_successful_sync_at' => (clone $sync)->where('status', 'success')->max('finished_at'),
        'sync_statuses_24h' => $statuses((clone $sync)->where('created_at', '>=', $since)),
        'last_webhook_received_at' => (clone $webhooks)->max('received_at'),
        'last_webhook_processed_at' => (clone $webhooks)->where('status', 'processed')->max('processed_at'),
        'webhook_statuses_24h' => $statuses((clone $webhooks)->where('received_at', '>=', $since)),
        'last_incoming_remote_at' => DB::table('avito_messages')->where('direction', 'in')->max('remote_created_at'),
        'last_ai_decision_at' => DB::table('avito_auto_reply_decisions')->max('created_at'),
        'ai_decisions_24h' => DB::table('avito_auto_reply_decisions')->where('created_at', '>=', $since)->count(),
        'ai_sent_24h' => DB::table('avito_auto_reply_decisions')->where('created_at', '>=', $since)->where('outcome', 'sent')->count(),
    ];
}

function failedJobsMetadata(): array
{
    if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
        return ['counts_inspection' => 'unsupported'];
    }
    $query = DB::connection(config('queue.failed.database'))->table(config('queue.failed.table', 'failed_jobs'));
    $result = [];
    foreach (['ProcessAvitoAutoReplyJob', 'SyncAvitoMessengerJob', 'ArchiveAvitoMessageMediaJob', 'AvitoDataChanged'] as $class) {
        $jobs = (clone $query)->where('payload', 'like', '%'.$class.'%');
        $result[$class] = ['count' => (clone $jobs)->count(), 'last_failed_at' => (clone $jobs)->max('failed_at')];
    }

    return ['counts_inspection' => 'complete', 'jobs' => $result];
}

function providerSubscriptions(): array
{
    $sources = [];
    if (filled(config('avito.client_id')) && filled(config('avito.client_secret'))) {
        $sources[] = null;
    }
    $oauth = AvitoConnection::query()->where('is_active', true);
    $sourceCount = count($sources) + (clone $oauth)->count();
    foreach ($oauth->orderBy('id')->limit(8)->get() as $connection) {
        $sources[] = $connection;
    }
    $capability = app(AvitoApiCatalog::class)->findOperation('messenger', 'getSubscriptions');
    $expected = route('api.avito.webhook');
    $secret = (string) config('avito.webhook_secret');
    $deadline = microtime(true) + 90;
    $rows = [];
    foreach ($sources as $connection) {
        $row = ['connection_id' => $connection?->id, 'source' => $connection ? 'oauth' : 'client_credentials'];
        if (microtime(true) >= $deadline) {
            $rows[] = $row + ['inspection' => 'time_budget_exceeded'];

            continue;
        }
        try {
            if (! config('avito.enabled')) {
                $rows[] = $row + ['inspection' => 'integration_disabled'];

                continue;
            }
            $token = app(AvitoTokenManager::class)->tokenFor($capability, $connection);
            // This is a read operation despite Avito using POST. Use the fixed
            // provider origin and disable redirects so tokens stay at Avito.
            $response = Http::withToken($token)->acceptJson()->timeout(10)->connectTimeout(4)
                ->withOptions(['allow_redirects' => false])->send('POST', 'https://api.avito.ru/messenger/v1/subscriptions');
            $items = $response->json('subscriptions');
            $valid = $response->successful() && is_array($items);
            $row += ['inspection' => $valid ? 'complete' : 'provider_error', 'http_status' => $response->status()];
            if ($valid) {
                $row += subscriptionSummary($items, $expected, $secret);
            }
        } catch (Throwable $exception) {
            $row += ['inspection' => 'unavailable', 'error_category' => $exception instanceof AvitoException ? code($exception->category) : 'request_failed'];
        }
        $rows[] = $row;
    }

    return ['configured_source_count' => $sourceCount, 'sources_omitted_by_limit' => max(0, $sourceCount - count($sources)), 'sources' => $rows];
}

function report(string $targetDir): array
{
    $connection = (string) config('queue.default');
    $queue = (string) config('queue.connections.'.$connection.'.queue', 'default');

    return [
        'checked_at' => gmdate(DATE_ATOM),
        'application_timezone' => (string) config('app.timezone'),
        'schema' => inspect(__NAMESPACE__.'\\schemaMetadata'),
        'systemd' => inspect(__NAMESPACE__.'\\serviceStatuses'),
        'workers' => inspect(static fn () => workers($targetDir)),
        'avito_queue' => inspect(static fn () => queueMetadata($connection, $queue)),
        'realtime_queue' => inspect(static fn () => queueMetadata('database', 'realtime')),
        'job_timeouts_seconds' => ['auto_reply' => 120, 'initial_chat_history' => 840, 'media_archive' => 120, 'manual_sync' => 1800],
        'failed_jobs' => inspect(__NAMESPACE__.'\\failedJobsMetadata'),
        'activity' => inspect(__NAMESPACE__.'\\activityMetadata'),
        'ai' => inspect(static fn () => safeDiagnostics(app(AvitoAutoReplyDiagnostics::class)->report(days: 90))),
        'webhook_subscriptions' => inspect(__NAMESPACE__.'\\providerSubscriptions'),
    ];
}

function main(array $arguments): int
{
    // Prevent a bootstrap warning or third-party stdout from exposing data in
    // Actions logs. Only the explicit allowlisted JSON report reaches stdout.
    ini_set('display_errors', '0');
    ob_start();
    $exit = 0;
    try {
        $targetDir = count($arguments) === 2 ? realpath($arguments[1]) : false;
        if ($targetDir === false || ! is_file($targetDir.'/bootstrap/app.php')) {
            throw new \RuntimeException;
        }
        require $targetDir.'/vendor/autoload.php';
        $app = require $targetDir.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $result = report($targetDir);
    } catch (Throwable) {
        $result = ['inspection' => 'unavailable', 'error_category' => 'application_bootstrap_failed'];
        $exit = 1;
    } finally {
        ob_end_clean();
    }
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL);

    return $exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main($argv));
}
