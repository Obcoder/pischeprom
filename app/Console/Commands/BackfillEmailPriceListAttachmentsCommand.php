<?php

namespace App\Console\Commands;

use App\Domain\AiPriceLists\Services\EmailPriceListIngestionDispatcher;
use App\Models\MailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackfillEmailPriceListAttachmentsCommand extends Command
{
    protected $signature = 'price-lists:mail-backfill
        {--apply : Поставить выбранные письма в очередь}
        {--limit=25 : Размер безопасного пакета, от 1 до 100}
        {--cursor= : Для следующего пакета: выбирать ID меньше указанного}
        {--mailbox= : Ограничить почтовым ящиком}
        {--since= : Не старше даты/времени}
        {--until= : Не новее даты/времени}';

    protected $description = 'Безопасно дозагружает вложения старых входящих писем в AI-модуль прайс-листов';

    public function handle(EmailPriceListIngestionDispatcher $dispatcher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->positiveIntegerOption('limit', 1, 100);
        $cursor = $this->optionalPositiveIntegerOption('cursor');

        if ($limit === null || $cursor === false) {
            return self::INVALID;
        }

        if ($apply && ! config('ai-price-lists.enabled')) {
            $this->error('AI-модуль прайс-листов выключен; apply остановлен.');

            return self::FAILURE;
        }

        try {
            $since = $this->dateOption('since', false);
            $until = $this->dateOption('until', true);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $mailbox = mb_strtolower(trim((string) $this->option('mailbox')));
        $query = $this->candidateQuery($cursor ?: null, $mailbox, $since, $until);
        $matching = (clone $query)->count();
        $ids = $query
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->table(['Режим', 'Найдено', 'В пакете', 'Диапазон ID', 'Останется'], [[
            $apply ? 'apply' : 'dry-run',
            $matching,
            count($ids),
            $ids === [] ? '—' : max($ids).'…'.min($ids),
            max(0, $matching - count($ids)),
        ]]);

        if ($ids === []) {
            $this->info('Подходящих писем в этом диапазоне нет.');

            return self::SUCCESS;
        }

        $nextCursor = min($ids);

        if (! $apply) {
            $this->comment('Состояние не изменялось. Для запуска этого пакета нужен --apply.');
            $this->line("Курсор следующего пакета: {$nextCursor}");

            return self::SUCCESS;
        }

        $messages = MailMessage::query()->whereKey($ids)->get()->keyBy('id');
        $dispatched = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $message = $messages->get($id);

            if ($message && $dispatcher->register($message)) {
                $dispatched++;
            } else {
                $skipped++;
            }
        }

        Log::info('AI price-list email backfill batch dispatched', [
            'selected' => count($ids),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'next_cursor' => $nextCursor,
            'mailbox_filtered' => $mailbox !== '',
            'since_filtered' => $since !== null,
            'until_filtered' => $until !== null,
        ]);

        $this->info("Поставлено в очередь: {$dispatched}; пропущено: {$skipped}.");
        $this->line("Курсор следующего пакета: {$nextCursor}");

        return self::SUCCESS;
    }

    private function candidateQuery(
        ?int $cursor,
        string $mailbox,
        ?CarbonImmutable $since,
        ?CarbonImmutable $until,
    ): Builder {
        return MailMessage::query()
            ->where('direction', 'incoming')
            ->where('has_attachments', true)
            ->whereNotNull('imap_uid')
            ->when($cursor, fn (Builder $query) => $query->where('id', '<', $cursor))
            ->when($mailbox !== '', fn (Builder $query) => $query->whereRaw('LOWER(mailbox) = ?', [$mailbox]))
            ->when($since, fn (Builder $query) => $query->where('message_date', '>=', $since))
            ->when($until, fn (Builder $query) => $query->where('message_date', '<=', $until));
    }

    private function positiveIntegerOption(string $name, int $minimum, int $maximum): ?int
    {
        $value = trim((string) $this->option($name));

        if (! preg_match('/^\d+$/', $value)) {
            $this->error("--{$name} должен быть целым числом.");

            return null;
        }

        $integer = (int) $value;

        if ($integer < $minimum || $integer > $maximum) {
            $this->error("--{$name} должен быть от {$minimum} до {$maximum}.");

            return null;
        }

        return $integer;
    }

    private function optionalPositiveIntegerOption(string $name): int|false|null
    {
        $value = trim((string) $this->option($name));

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^[1-9]\d*$/', $value)) {
            $this->error("--{$name} должен быть положительным целым числом.");

            return false;
        }

        return (int) $value;
    }

    private function dateOption(string $name, bool $endOfDay): ?CarbonImmutable
    {
        $value = trim((string) $this->option($name));

        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw new \InvalidArgumentException("--{$name} содержит некорректную дату.");
        }

        return $endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? $date->endOfDay()
            : $date;
    }
}
