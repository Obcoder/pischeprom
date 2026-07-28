<?php

namespace App\Services\Logistics;

use App\Enums\Logistics\RoutingRunStatus;
use App\Enums\Logistics\RoutingRunType;
use App\Models\LogisticsRoutingRun;
use Illuminate\Support\Facades\DB;

class RoutingRunService
{
    public function create(
        RoutingRunType $type,
        string $profile,
        int $totalPairs,
        ?int $initiatedBy,
        array $parameters = [],
    ): LogisticsRoutingRun {
        return LogisticsRoutingRun::query()->create([
            'operation_type' => $type,
            'status' => $totalPairs === 0 ? RoutingRunStatus::Completed : RoutingRunStatus::Queued,
            'routing_profile' => $profile,
            'total_pairs' => $totalPairs,
            'initiated_by' => $initiatedBy,
            'parameters' => [
                ...$parameters,
                'completed_batches' => [],
            ],
            'finished_at' => $totalPairs === 0 ? now() : null,
        ]);
    }

    public function start(LogisticsRoutingRun|string $run): void
    {
        $id = $run instanceof LogisticsRoutingRun ? $run->id : $run;

        LogisticsRoutingRun::query()
            ->whereKey($id)
            ->where('status', RoutingRunStatus::Queued->value)
            ->update([
                'status' => RoutingRunStatus::Running->value,
                'started_at' => now(),
            ]);
    }

    public function completeBatch(
        LogisticsRoutingRun|string $run,
        string $batchId,
        int $completed,
        int $failed = 0,
        ?string $lastError = null,
    ): void {
        $id = $run instanceof LogisticsRoutingRun ? $run->id : $run;

        DB::transaction(function () use ($id, $batchId, $completed, $failed, $lastError): void {
            $run = LogisticsRoutingRun::query()->lockForUpdate()->find($id);

            if (! $run || in_array($run->status, [RoutingRunStatus::Cancelled, RoutingRunStatus::Completed], true)) {
                return;
            }

            $parameters = $run->parameters ?? [];
            $completedBatches = $parameters['completed_batches'] ?? [];

            if (in_array($batchId, $completedBatches, true)) {
                return;
            }

            $completedBatches[] = $batchId;
            $parameters['completed_batches'] = array_values($completedBatches);
            $run->completed_pairs = min($run->total_pairs, $run->completed_pairs + max(0, $completed));
            $run->failed_pairs = min($run->total_pairs, $run->failed_pairs + max(0, $failed));
            $run->parameters = $parameters;
            $run->last_error = $lastError ?: $run->last_error;
            $run->started_at ??= now();

            if (($run->completed_pairs + $run->failed_pairs) >= $run->total_pairs) {
                $run->status = match (true) {
                    $run->completed_pairs === 0 && $run->failed_pairs > 0 => RoutingRunStatus::Failed,
                    $run->failed_pairs > 0 => RoutingRunStatus::Partial,
                    default => RoutingRunStatus::Completed,
                };
                $run->finished_at = now();
            } else {
                $run->status = RoutingRunStatus::Running;
            }

            $run->save();
        }, 3);
    }

    public function failRun(LogisticsRoutingRun|string $run, string $safeError): void
    {
        $id = $run instanceof LogisticsRoutingRun ? $run->id : $run;

        LogisticsRoutingRun::query()->whereKey($id)->update([
            'status' => RoutingRunStatus::Failed->value,
            'finished_at' => now(),
            'last_error' => $safeError,
        ]);
    }
}
