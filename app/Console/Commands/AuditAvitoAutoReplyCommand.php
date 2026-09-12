<?php

namespace App\Console\Commands;

use App\Services\Avito\AutoReply\AvitoAutoReplyDiagnostics;
use Illuminate\Console\Command;
use Throwable;

class AuditAvitoAutoReplyCommand extends Command
{
    protected $signature = 'avito:auto-reply-audit {--days=30 : Period for decision and incoming-message counts (1–365)} {--json : Output metadata as JSON}';

    protected $description = 'Read-only audit of Avito auto-reply configuration, activity, and queue metadata';

    public function handle(AvitoAutoReplyDiagnostics $diagnostics): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        if ($days === false) {
            $this->error('--days должен быть целым числом от 1 до 365.');

            return self::INVALID;
        }
        try {
            $report = $diagnostics->report(days: $days);
        } catch (Throwable) {
            $this->error('Не удалось прочитать состояние автоответов. Проверьте доступ к базе данных и миграции. Значения подключения и исключение скрыты.');

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $this->info('Аудит выполнен без изменения данных, запросов к Avito/AI и отправки сообщений.');
        $this->line('Режим: '.($report['mode'] ?? 'не определён').'; ответы: '.($report['response_mode'] ?? 'не определён'));
        foreach ($report['blockers'] as $issue) {
            $this->warn('Препятствие: '.$issue['message']);
        }
        foreach ($report['warnings'] as $issue) {
            $this->line('Проверить: '.$issue['message']);
        }
        if (isset($report['activity'])) {
            $this->table(['Активность', 'Значение'], collect($report['activity'])->map(fn ($value, $key) => [$key, $value ?? '—'])->values()->all());
            $this->table(['Результат', 'Причина', 'Количество'], array_map(fn ($row) => [$row['outcome'], $row['reason_code'] ?? '—', $row['total']], $report['reason_counts']));
            $this->line('Очередь: '.json_encode($report['queue'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
