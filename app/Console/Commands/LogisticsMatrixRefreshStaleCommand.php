<?php

namespace App\Console\Commands;

use App\Services\Logistics\CityDistanceMatrixService;
use Illuminate\Console\Command;
use Throwable;

class LogisticsMatrixRefreshStaleCommand extends Command
{
    protected $signature = 'logistics:matrix-refresh-stale
        {--cities=* : Optional city IDs; both endpoints must belong to this selection}
        {--profile=truck : Routing profile: truck or auto}
        {--limit=500 : Maximum number of directed pairs to queue}
        {--include-failed : Also retry records in failed state}
        {--dry-run : Count eligible records without writing or queueing}';

    protected $description = 'Queue an exact bounded set of stale or expired directed matrix pairs';

    public function handle(CityDistanceMatrixService $matrix): int
    {
        $profile = trim((string) $this->option('profile'));
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $cityIds = $this->cityIds();
        $includeFailed = (bool) $this->option('include-failed');

        if (! in_array($profile, ['truck', 'auto'], true)) {
            $this->error('--profile must be truck or auto.');

            return self::INVALID;
        }

        if ($limit === false || $limit < 1 || $limit > 10_000) {
            $this->error('--limit must be an integer between 1 and 10000.');

            return self::INVALID;
        }

        if ($this->option('cities') !== [] && count($cityIds) < 2) {
            $this->error('When --cities is used, provide at least two valid positive city IDs.');

            return self::INVALID;
        }

        try {
            $eligible = $matrix->countStalePairs($profile, $cityIds, $includeFailed);

            if ($this->option('dry-run')) {
                $this->info(sprintf(
                    'Dry run: eligible=%d, would_queue=%d, profile=%s. No rows or jobs were created.',
                    $eligible,
                    min($eligible, $limit),
                    $profile,
                ));

                return self::SUCCESS;
            }

            $run = $matrix->enqueueStalePairs($profile, $limit, $cityIds, $includeFailed);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Routing run %s queued with %d directed pairs (%d eligible before the limit).',
            $run->id,
            $run->total_pairs,
            $eligible,
        ));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function cityIds(): array
    {
        return collect($this->option('cities'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => filter_var(trim((string) $value), FILTER_VALIDATE_INT))
            ->filter(fn ($value) => $value !== false && $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
