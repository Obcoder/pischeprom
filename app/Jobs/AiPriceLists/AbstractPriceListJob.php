<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\MaxPriceListNotifier;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Jobs\Middleware\ObservePriceListJob;
use App\Models\PriceListImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

abstract class AbstractPriceListJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $importId)
    {
        $this->tries = max(1, (int) config('ai-price-lists.limits.max_attempts'));
        $this->timeout = max(30, (int) config('ai-price-lists.limits.timeout_seconds') + 60);
        $this->onConnection((string) config('ai-price-lists.queue_connection'));
        $this->onQueue((string) config('ai-price-lists.queue'));
    }

    public function uniqueId(): string
    {
        return static::class.':'.$this->importId;
    }

    public function middleware(): array
    {
        return [
            new ObservePriceListJob,
            (new WithoutOverlapping('price-list:'.$this->importId.':'.static::class))->expireAfter($this->timeout + 30),
        ];
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function failed(?Throwable $exception): void
    {
        $import = PriceListImport::query()->find($this->importId);

        if (! $import || in_array($import->status, [PriceListStatus::Applied, PriceListStatus::Cancelled, PriceListStatus::NotAPriceList], true)) {
            return;
        }

        try {
            $failed = app(PriceListStateMachine::class)->fail(
                $import,
                'job_failed',
                'Этап не выполнен после нескольких попыток. Его можно безопасно повторить.',
                true,
                ['job' => static::class],
            );
            app(MaxPriceListNotifier::class)->failed($failed, 'Прайс-лист не удалось обработать. Файл сохранён, сотрудник сможет повторить этап.');
        } catch (Throwable) {
            // The queue failure callback must not hide the original exception.
        }
    }

    protected function dispatchNext(AbstractPriceListJob $job): void
    {
        dispatch($job->afterCommit());
    }
}
