<?php

namespace App\Jobs\Logistics;

use App\Models\LogisticsTrip;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use App\Services\Logistics\RoutingRunService;
use App\Services\Logistics\TripRouteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

class CalculateTripRouteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $tripId,
        public readonly string $runId,
        public readonly ?int $createdBy = null,
        public readonly bool $force = false,
    ) {
        $this->onQueue((string) config('logistics.queue', 'routing'));
        if (config('logistics.queue_connection')) {
            $this->onConnection((string) config('logistics.queue_connection'));
        }
    }

    public function uniqueId(): string
    {
        return 'trip:'.$this->tripId;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(TripRouteService $routes, RoutingRunService $runs): void
    {
        $runs->start($this->runId);
        $trip = LogisticsTrip::query()->findOrFail($this->tripId);

        try {
            $routes->calculate($trip, $this->createdBy, $this->force);
            $runs->completeBatch($this->runId, $this->batchId(), 1);
        } catch (RoutingException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $runs->completeBatch($this->runId, $this->batchId(), 0, 1, $exception->getMessage());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Маршрут рейса нельзя рассчитать из-за неполных данных.';
            $runs->completeBatch($this->runId, $this->batchId(), 0, 1, (string) $message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof RoutingException
            ? $exception->getMessage()
            : 'Не удалось рассчитать маршрут рейса после повторных попыток.';
        app(RoutingRunService::class)->completeBatch($this->runId, $this->batchId(), 0, 1, $message);
    }

    private function batchId(): string
    {
        return hash('sha256', $this->runId.':trip:'.$this->tripId);
    }
}
