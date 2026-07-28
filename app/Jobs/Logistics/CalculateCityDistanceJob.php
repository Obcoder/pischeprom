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

class CalculateCityDistanceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly string $runId,
        public readonly array $pair,
        public readonly string $routingProfile = 'truck',
    ) {
        $this->onQueue((string) config('logistics.queue', 'routing'));
        if (config('logistics.queue_connection')) {
            $this->onConnection((string) config('logistics.queue_connection'));
        }
    }

    public function uniqueId(): string
    {
        return sprintf(
            'city:%d:%d:%s',
            $this->pair['from_city_id'],
            $this->pair['to_city_id'],
            $this->routingProfile,
        );
    }

    public function handle(CityDistanceMatrixService $matrix, RoutingRunService $runs): void
    {
        $runs->start($this->runId);
        $batchId = hash('sha256', $this->runId.$this->uniqueId());

        try {
            $result = $matrix->calculateQueuedPairs([$this->pair], $this->routingProfile);
            $runs->completeBatch($this->runId, $batchId, $result['completed'], $result['failed']);
        } catch (RoutingException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $matrix->markPairsFailed([$this->pair], $this->routingProfile, $exception->domainCode, $exception->getMessage());
            $runs->completeBatch($this->runId, $batchId, 0, 1, $exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof RoutingException
            ? $exception->getMessage()
            : 'Не удалось рассчитать расстояние после повторных попыток.';
        app(CityDistanceMatrixService::class)->markPairsFailed(
            [$this->pair],
            $this->routingProfile,
            $exception instanceof RoutingException ? $exception->domainCode : 'job_failed',
            $message,
        );
        app(RoutingRunService::class)->completeBatch(
            $this->runId,
            hash('sha256', $this->runId.$this->uniqueId()),
            0,
            1,
            $message,
        );
    }
}
