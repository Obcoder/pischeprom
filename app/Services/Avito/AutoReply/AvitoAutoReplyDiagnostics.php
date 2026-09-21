<?php

namespace App\Services\Avito\AutoReply;

use App\Jobs\Avito\BootstrapAvitoChatHistoryJob;
use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Models\AvitoWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AvitoAutoReplyDiagnostics
{
    public function __construct(
        private readonly AvitoAutoReplyClassifier $classifier,
        private readonly AvitoAutoReplySafetyGuard $guard,
    ) {}

    /** Inspect local configuration and stored metadata; never call Avito/AI or dispatch jobs. */
    public function report(?AvitoChat $chat = null, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $report = [
            'checked_at' => now()->toIso8601String(),
            'period_days' => $days,
            'chat_id' => $chat?->id,
            'blockers' => [],
            'warnings' => [],
            'ready_for_sending' => false,
            'classifier_configured' => $this->classifier->configured(),
            'avito_enabled' => (bool) config('avito.enabled'),
            'mutations_enabled' => (bool) config('avito.mutations_enabled'),
            'client_credentials_configured' => filled(config('avito.client_id')) && filled(config('avito.client_secret')),
            'webhook_secret_configured' => filled(config('avito.webhook_secret')),
        ];
        foreach (['avito_auto_reply_settings', 'avito_auto_reply_rules', 'avito_auto_reply_decisions', 'avito_messenger_accounts', 'avito_connections', 'avito_chats', 'avito_messages', 'avito_webhook_events'] as $table) {
            if (! Schema::hasTable($table)) {
                $report['blockers'][] = $this->issue('schema_missing', "Не применены миграции: отсутствует таблица {$table}.");
            }
        }
        if ($report['blockers'] !== []) {
            return $report;
        }
        foreach ([
            'avito_auto_reply_settings' => ['response_mode', 'emergency_stopped_at'],
            'avito_auto_reply_decisions' => ['response_text', 'matched_rule_keys'],
            'avito_chats' => ['history_synced_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $report['blockers'][] = $this->issue('schema_column_missing', "Не применены миграции: отсутствует поле {$table}.{$column}.");
                }
            }
        }
        if ($report['blockers'] !== []) {
            return $report;
        }

        // Unlike current(), this read does not insert default settings during an audit.
        $settings = AvitoAutoReplySetting::query()->find(1);
        $mode = $settings?->mode ?? 'shadow';
        $report['mode'] = $mode;
        $report['response_mode'] = $settings?->response_mode ?? 'assistant';
        $report['emergency_stopped_at'] = $settings?->emergency_stopped_at?->toIso8601String();
        if ($settings?->emergency_stopped_at) {
            $report['blockers'][] = $this->issue('emergency_stopped', 'AI экстренно остановлен оператором. Сначала снимите остановку кнопкой «Возобновить наблюдение», затем отдельно включите отправку.');
        }
        if (in_array($mode, ['off', 'shadow'], true)) {
            $report['blockers'][] = $this->issue($mode === 'off' ? 'mode_off' : 'shadow_mode', $mode === 'off'
                ? 'Автоответы выключены. Входящие сообщения не получают автоматического ответа.'
                : 'Включено наблюдение: AI записывает решения и проекты ответов, но ничего не отправляет. Для отправки нужен режим «Пилот» или «Активно».');
        }
        foreach ([
            'classifier_configured' => ['classifier_not_configured', 'Yandex AI Studio не настроен: проверьте ключ, каталог и модель.'],
            'avito_enabled' => ['avito_disabled', 'Интеграция Avito отключена в конфигурации.'],
            'mutations_enabled' => ['mutations_disabled', 'Отправка сообщений в Avito запрещена конфигурацией.'],
        ] as $field => [$code, $message]) {
            if (! $report[$field]) {
                $report['blockers'][] = $this->issue($code, $message);
            }
        }

        $rules = AvitoAutoReplyRule::query()->get();
        $active = $rules->where('is_active', true)->where('is_approved', true);
        $scoped = $active->filter(fn (AvitoAutoReplyRule $rule) => $rule->appliesTo($chat));
        $candidates = $mode === 'pilot' ? $scoped->where('is_pilot', true) : $scoped;
        $eligible = $candidates->filter(fn (AvitoAutoReplyRule $rule) => $this->guard->responseBlockedReason((string) $rule->response_text, collect([$rule])) === null);
        $unsafeCount = $candidates->count() - $eligible->count();
        $report['rules'] = [
            'total' => $rules->count(),
            'active_approved' => $active->count(),
            'in_scope' => $scoped->count(),
            'pilot_in_scope' => $scoped->where('is_pilot', true)->count(),
            'eligible_before_safety' => $candidates->count(),
            'unsafe_responses' => $unsafeCount,
            'eligible' => $eligible->count(),
        ];
        if ($unsafeCount > 0) {
            $report['warnings'][] = $this->issue('unsafe_rule_responses', "Сценариев с запрещёнными сведениями в утверждённом ответе: {$unsafeCount}. Защитный фильтр исключает их из ответов AI; исправьте текст сценариев.");
        }
        if ($eligible->isEmpty()) {
            $report['blockers'][] = $this->issue('no_eligible_rules', $unsafeCount > 0
                ? 'Все подходящие сценарии исключены защитной проверкой ответов. Исправьте запрещённые сведения в их тексте.'
                : ($mode === 'pilot'
                    ? 'Нет активных утверждённых пилотных сценариев для выбранного контекста.'
                    : 'Нет активных утверждённых сценариев для выбранного контекста. Проверьте аккаунты и ID объявлений в правилах.'));
        }

        $accounts = AvitoMessengerAccount::query()
            ->with('connection:id,is_active,access_token,refresh_token,token_expires_at,scopes')
            ->when($chat, fn ($query) => $query->whereKey($chat->avito_messenger_account_id))
            ->orderBy('id')
            ->get(['id', 'avito_connection_id', 'name', 'sync_enabled', 'sync_status', 'last_synced_at']);
        $report['accounts'] = $accounts->map(function (AvitoMessengerAccount $account) use ($report): array {
            $connection = $account->connection;
            $credentialsReady = $connection
                ? $connection->is_active && filled($connection->getRawOriginal('access_token'))
                    && (! $connection->token_expires_at?->lte(now()->addMinute())
                        || (filled($connection->getRawOriginal('refresh_token')) && $report['client_credentials_configured']))
                : ! $account->avito_connection_id && $report['client_credentials_configured'];

            return [
                'id' => $account->id,
                'name' => $account->name ?: "Аккаунт {$account->id}",
                'sync_enabled' => $account->sync_enabled,
                'sync_status' => $account->sync_status,
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                'credentials_ready' => (bool) $credentialsReady,
                'write_scope_present' => $connection ? in_array('messenger:write', $connection->scopes ?? [], true) : null,
            ];
        })->all();
        if ($accounts->isEmpty()) {
            $report['blockers'][] = $this->issue('no_messenger_accounts', 'Нет подключённых аккаунтов мессенджера Avito.');
        } elseif (! collect($report['accounts'])->contains('credentials_ready', true)) {
            $report['blockers'][] = $this->issue('avito_credentials_missing', 'Ни у одного аккаунта в выбранном контексте нет готовых учётных данных Avito.');
        } elseif (! collect($report['accounts'])->contains(fn (array $account) => $account['credentials_ready'] && $account['write_scope_present'] !== false)) {
            $report['blockers'][] = $this->issue('avito_write_scope_missing', 'OAuth-подключениям не хватает права messenger:write для отправки сообщений.');
        }
        if ($accounts->isNotEmpty() && $eligible->isNotEmpty()
            && ! $eligible->contains(fn (AvitoAutoReplyRule $rule) => empty($rule->account_ids) || array_intersect(array_map('intval', $rule->account_ids), $accounts->modelKeys()) !== [])) {
            $report['blockers'][] = $this->issue('rules_account_scope_mismatch', 'Подходящие сценарии ограничены аккаунтами, которых нет среди подключений мессенджера.');
        }
        foreach ($report['accounts'] as $account) {
            if (! $account['sync_enabled'] || ! $account['last_synced_at'] || $account['sync_status'] === 'error') {
                $report['warnings'][] = $this->issue('account_sync_unhealthy', "{$account['name']}: проверьте включение и последние результаты синхронизации.");
            }
            if (! $account['credentials_ready']) {
                $report['warnings'][] = $this->issue('account_credentials_missing', "{$account['name']}: отсутствуют готовые учётные данные Avito.");
            }
            if ($account['write_scope_present'] === false) {
                $report['warnings'][] = $this->issue('write_scope_unconfirmed', "{$account['name']}: в сохранённых OAuth-правах нет messenger:write; доступ на стороне Avito не проверен.");
            }
        }
        if (! $report['webhook_secret_configured']) {
            $report['warnings'][] = $this->issue('webhook_not_configured', 'Секрет webhook не задан: проверьте поступление событий и регулярную синхронизацию мессенджера.');
        }

        $since = now()->subDays($days);
        $incoming = AvitoMessage::query()->where('direction', 'in')
            ->when($chat, fn ($query) => $query->where('avito_chat_id', $chat->id));
        $decisions = AvitoAutoReplyDecision::query()
            ->when($chat, fn ($query) => $query->where('avito_chat_id', $chat->id));
        $report['activity'] = [
            'last_incoming_at' => (clone $incoming)->max('remote_created_at'),
            'last_incoming_imported_at' => (clone $incoming)->max('created_at'),
            'last_webhook_at' => AvitoWebhookEvent::query()->max('received_at'),
            'last_decision_at' => (clone $decisions)->max('created_at'),
            'incoming_count' => (clone $incoming)->where('created_at', '>=', $since)->count(),
            'incoming_without_decision' => (clone $incoming)->where('created_at', '>=', $since)->whereDoesntHave('autoReplyDecisions')->count(),
            'decision_count' => (clone $decisions)->where('created_at', '>=', $since)->count(),
            'sending_stalled_count' => (clone $decisions)->where('outcome', 'sending')->where('updated_at', '<', now()->subSeconds(150))->count(),
            'webhook_error_count' => AvitoWebhookEvent::query()->where('received_at', '>=', $since)->where('status', 'error')->count(),
        ];
        $report['reason_counts'] = (clone $decisions)->where('created_at', '>=', $since)
            ->selectRaw('outcome, reason_code, count(*) as total')
            ->groupBy('outcome', 'reason_code')->orderByDesc('total')->get()
            ->map(fn ($row) => ['outcome' => $row->outcome, 'reason_code' => $row->reason_code, 'total' => (int) $row->total])->all();
        if ($report['activity']['incoming_count'] > 0 && $report['activity']['decision_count'] === 0) {
            $report['warnings'][] = $this->issue('incoming_without_decisions', 'Есть входящие сообщения, но за выбранный период нет решений AI. Проверьте запуск заданий и worker очереди; сообщения из первоначального импорта могут намеренно не анализироваться.');
        }

        $report['queue'] = $this->queueReport();
        foreach (['pending', 'failed'] as $kind) {
            if (isset($report['queue'][$kind]) && $report['queue'][$kind]['status'] !== 'inspected') {
                $report['warnings'][] = $this->issue('queue_inspection_unavailable', 'Не удалось прочитать часть метаданных очереди: проверьте подключение и таблицы заданий на сервере.');
                break;
            }
        }
        if ($report['activity']['sending_stalled_count'] > 0) {
            $report['warnings'][] = $this->issue('sending_stalled', 'Есть отправки с неизвестным результатом дольше 150 секунд. Проверьте переписку в Avito: повторная автоматическая отправка заблокирована.');
        }
        if (in_array($report['queue']['driver'], ['database', 'redis', 'beanstalkd'], true)
            && ($report['queue']['retry_after'] ?? 0) <= 840) {
            $report['warnings'][] = $this->issue('queue_retry_after_too_short', 'retry_after очереди должен быть больше таймаута первоначального импорта переписки (840 секунд), иначе worker может повторно взять ещё выполняемое задание.');
        }
        if ($report['queue']['driver'] === 'sync') {
            $report['warnings'][] = $this->issue('queue_sync', 'Очередь sync выполняет анализ внутри запроса и не выдерживает задержку сбора сообщений. Используйте database или Redis и работающий queue worker.');
        } elseif ($report['queue']['driver'] === 'null') {
            $report['blockers'][] = $this->issue('queue_disabled', 'Очередь null отбрасывает задания автоответов.');
        } else {
            $report['warnings'][] = $this->issue('queue_worker_unverified', 'Работу queue worker нельзя подтвердить по настройкам. Проверьте его процесс и очередь; просроченные задания могут пропускать окно свежести сообщения.');
        }
        if (($report['queue']['pending']['overdue'] ?? 0) > 0) {
            $report['warnings'][] = $this->issue('queue_overdue', 'В очереди есть задания автоответов, ожидающие более 5 минут после запланированного запуска.');
        }
        if (($report['queue']['failed']['count'] ?? 0) > 0) {
            $report['warnings'][] = $this->issue('queue_failed', 'В журнале очереди есть упавшие задания автоответов. Требуется проверка причин на сервере.');
        }
        $report['ready_for_sending'] = $report['blockers'] === [];

        return $report;
    }

    private function queueReport(): array
    {
        $name = (string) config('queue.default');
        $config = (array) config("queue.connections.{$name}", []);
        $report = ['connection' => $name, 'driver' => $config['driver'] ?? 'unknown', 'retry_after' => $config['retry_after'] ?? null, 'worker_verified' => false];
        if ($report['driver'] === 'database') {
            $report['pending'] = $this->databaseJobs($config['connection'] ?? null, $config['table'] ?? 'jobs');
        }
        if (in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
            $report['failed'] = $this->databaseJobs(config('queue.failed.database'), config('queue.failed.table', 'failed_jobs'), true);
        }

        return $report;
    }

    private function databaseJobs(?string $connection, string $table, bool $failed = false): array
    {
        try {
            if (! Schema::connection($connection)->hasTable($table)) {
                return ['status' => 'table_missing'];
            }
            $jobClasses = [ProcessAvitoAutoReplyJob::class, BootstrapAvitoChatHistoryJob::class];
            $query = DB::connection($connection)->table($table)->where(fn ($query) => $query
                ->where('payload', 'like', '%ProcessAvitoAutoReplyJob%')
                ->orWhere('payload', 'like', '%BootstrapAvitoChatHistoryJob%'));
            $candidateCount = (clone $query)->count();
            $rows = $query->orderBy('id')->limit(1000)->get($failed
                ? ['payload', 'failed_at']
                : ['payload', 'available_at', 'reserved_at']);
            $count = $overdue = 0;
            $lastFailedAt = null;
            foreach ($rows as $row) {
                // Decode JSON only. Never unserialize a command, expose payloads, or read exceptions.
                $payload = json_decode($row->payload, true);
                if (! is_array($payload) || (! in_array($payload['displayName'] ?? null, $jobClasses, true)
                    && ! in_array($payload['data']['commandName'] ?? null, $jobClasses, true))) {
                    continue;
                }
                $count++;
                if ($failed) {
                    $lastFailedAt = max($lastFailedAt ?? '', $row->failed_at);
                } elseif ($row->available_at < now()->subMinutes(5)->timestamp) {
                    $overdue++;
                }
            }

            return ['status' => 'inspected', 'count' => $count, 'overdue' => $overdue, 'last_failed_at' => $lastFailedAt, 'truncated' => $candidateCount > 1000];
        } catch (Throwable) {
            return ['status' => 'unavailable'];
        }
    }

    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
