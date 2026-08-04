<?php

namespace App\Console\Commands;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Jobs\AiPriceLists\ApplyConfirmedPriceList;
use App\Jobs\AiPriceLists\ExtractPriceListContent;
use App\Jobs\AiPriceLists\FinalizePriceListForReview;
use App\Jobs\AiPriceLists\MatchPriceListItems;
use App\Jobs\AiPriceLists\NormalizePriceListRows;
use App\Jobs\AiPriceLists\RecognizePriceListWithOcr;
use App\Jobs\AiPriceLists\ValidatePriceListFile;
use App\Models\PriceListImport;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecoverStalePriceListImportsCommand extends Command
{
    protected $signature = 'price-lists:recover-stale {--dry-run : Только показать зависшие импорты}';

    protected $description = 'Возвращает в очередь зависшие retryable-этапы импорта прайс-листов';

    public function handle(PriceListAuditLogger $audit, PriceListStateMachine $states): int
    {
        $cutoff = now()->subMinutes(max(5, (int) config('ai-price-lists.recovery.stale_after_minutes')));
        $maxRecoveries = max(1, (int) config('ai-price-lists.recovery.max_recoveries'));
        $recovered = 0;
        $failed = 0;

        PriceListImport::query()
            ->whereIn('status', [
                PriceListStatus::Queued->value,
                PriceListStatus::Validating->value,
                PriceListStatus::Extracting->value,
                PriceListStatus::Ocr->value,
                PriceListStatus::Normalizing->value,
                PriceListStatus::Matching->value,
                PriceListStatus::Applying->value,
            ])
            ->where(fn ($query) => $query
                ->where('stage_heartbeat_at', '<', $cutoff)
                ->orWhere(fn ($missing) => $missing->whereNull('stage_heartbeat_at')->where('updated_at', '<', $cutoff)))
            ->orderBy('id')
            ->chunkById(100, function ($imports) use ($audit, $states, $maxRecoveries, &$recovered, &$failed): void {
                foreach ($imports as $import) {
                    $attempts = $import->events()->where('event_type', 'recovery_dispatched')->count();
                    $job = $this->jobFor($import);

                    $this->line(sprintf('%s · %s · recovery %d/%d', $import->uuid, $import->status->value, $attempts + 1, $maxRecoveries));

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    if (! $job) {
                        $states->fail($import, 'recovery_missing_actor', 'Зависшее применение нельзя восстановить без ответственного пользователя.', false);
                        $failed++;

                        continue;
                    }

                    if ($attempts >= $maxRecoveries) {
                        $states->fail($import, 'recovery_exhausted', 'Этап несколько раз зависал и требует ручной диагностики.', false);
                        $failed++;

                        continue;
                    }

                    $audit->record($import, 'recovery_dispatched', [
                        'attempt' => $attempts + 1,
                        'job' => $job::class,
                    ], stage: $import->current_stage);
                    $import->forceFill(['stage_heartbeat_at' => now()])->save();
                    dispatch($job->afterCommit());
                    $recovered++;
                }
            });

        $this->info($this->option('dry-run')
            ? 'Проверка завершена, состояние не изменялось.'
            : "Возвращено в очередь: {$recovered}; остановлено для диагностики: {$failed}.");

        return self::SUCCESS;
    }

    private function jobFor(PriceListImport $import): ?ShouldQueue
    {
        return match ($import->status) {
            PriceListStatus::Queued, PriceListStatus::Validating => new ValidatePriceListFile($import->id),
            PriceListStatus::Extracting => new ExtractPriceListContent($import->id),
            PriceListStatus::Ocr => new RecognizePriceListWithOcr($import->id),
            PriceListStatus::Normalizing => new NormalizePriceListRows($import->id),
            PriceListStatus::Matching => $import->current_stage === PriceListStage::Finalize->value
                ? new FinalizePriceListForReview($import->id)
                : new MatchPriceListItems($import->id),
            PriceListStatus::Applying => $import->applied_by
                ? new ApplyConfirmedPriceList($import->id, $import->applied_by)
                : null,
            default => null,
        };
    }
}
