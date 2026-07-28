<?php

namespace App\Jobs\Logistics;

use App\Services\Logistics\CityDistanceMatrixService;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use App\Services\Logistics\RoutingRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CalculateDistanceMatrixBatchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $runId,
        public readonly array $pairs,
        public readonly string $routingProfile,
    ) {
        $this->onQueue((string) config('logistics.queue', 'routing'));
        if (config('logistics.queue_connection')) {
            $this->onConnection((string) config('logistics.queue_connection'));
        }
    }

    public function uniqueId(): string
    {
        $pairs = collect($this->pairs)
            ->map(fn (array $pair) => (string) $pair['request_hash'])
            ->sort()
            ->values()
            ->all();

        return 'matrix:'.hash('sha256', $this->routingProfile.json_encode($pairs, JSON_THROW_ON_ERROR));
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(CityDistanceMatrixService $matrix, RoutingRunService $runs): void
    {
        $runs->start($this->runId);

        try {
            $result = $matrix->calculateQueuedPairs($this->pairs, $this->routingProfile);
            $runs->completeBatch(
                $this->runId,
                $this->batchId(),
                $result['completed'],
                $result['failed'],
                $result['failed'] > 0
                    ? 'Часть пар не рассчитана: маршрут не найден или исходные данные изменились.'
                    : null,
            );
        } catch (RoutingException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $matrix->markPairsFailed($this->pairs, $this->routingProfile, $exception->domainCode, $exception->getMessage());
            $runs->completeBatch($this->runId, $this->batchId(), 0, count($this->pairs), $exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof RoutingException
            ? $exception->getMessage()
            : 'Не удалось рассчитать пакет матрицы после повторных попыток.';
        $code = $exception instanceof RoutingException ? $exception->domainCode : 'job_failed';

        app(CityDistanceMatrixService::class)->markPairsFailed(
            $this->pairs,
            $this->routingProfile,
            $code,
            $message,
        );
        app(RoutingRunService::class)->completeBatch(
            $this->runId,
            $this->batchId(),
            0,
            count($this->pairs),
            $message,
        );
    }

    private function batchId(): string
    {
        return hash('sha256', $this->runId.json_encode($this->pairs, JSON_THROW_ON_ERROR));
    }
}
