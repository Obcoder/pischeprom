<?php

namespace App\Console\Commands;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RoutingRunStatus;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsRoutingRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LogisticsRoutingRecoverStuckCommand extends Command
{
    protected $signature = 'logistics:routing-recover-stuck
        {--older-than=15 : Minimum age in minutes for queued/running work}
        {--dry-run : Count affected rows without changing them}';

    protected $description = 'Fail abandoned routing runs and release their pending matrix pairs for retry';

    public function handle(): int
    {
        $olderThan = filter_var($this->option('older-than'), FILTER_VALIDATE_INT);

        if ($olderThan === false || $olderThan < 1 || $olderThan > 10_080) {
            $this->error('--older-than must be an integer between 1 and 10080.');

            return self::INVALID;
        }

        $cutoff = now()->subMinutes($olderThan);
        $runQuery = LogisticsRoutingRun::query()
            ->whereIn('status', [
                RoutingRunStatus::Queued->value,
                RoutingRunStatus::Running->value,
            ])
            ->where('updated_at', '<=', $cutoff);
        $distanceQuery = LogisticsCityDistance::query()
            ->where('status', DistanceStatus::Pending->value)
            ->where('updated_at', '<=', $cutoff);
        $runCount = (clone $runQuery)->count();
        $distanceCount = (clone $distanceQuery)->count();

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: stuck_runs=%d, pending_pairs=%d. No rows were changed.',
                $runCount,
                $distanceCount,
            ));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($runQuery, $distanceQuery): void {
            $message = 'Предыдущий расчёт не был обработан очередью и освобождён для повторного запуска.';

            $distanceQuery->update([
                'status' => DistanceStatus::Failed->value,
                'error_code' => 'stuck_run_recovered',
                'error_message' => $message,
                'calculated_at' => now(),
            ]);

            $runQuery->lockForUpdate()->get()->each(function (LogisticsRoutingRun $run) use ($message): void {
                $run->forceFill([
                    'status' => RoutingRunStatus::Failed,
                    'failed_pairs' => max(0, $run->total_pairs - $run->completed_pairs),
                    'finished_at' => now(),
                    'last_error' => $message,
                ])->save();
            });
        }, 3);

        $this->info(sprintf(
            'Recovered stuck routing work: runs=%d, matrix_pairs=%d.',
            $runCount,
            $distanceCount,
        ));

        return self::SUCCESS;
    }
}
