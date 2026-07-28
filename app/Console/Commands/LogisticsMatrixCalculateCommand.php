<?php

namespace App\Console\Commands;

use App\Models\LogisticsCity;
use App\Services\Logistics\CityDistanceMatrixService;
use Illuminate\Console\Command;
use Throwable;

class LogisticsMatrixCalculateCommand extends Command
{
    protected $signature = 'logistics:matrix-calculate
        {--cities=* : Explicit city IDs; comma-separated values are accepted}
        {--all : Calculate the full matrix for every enabled city with verified routing coordinates}
        {--profile=truck : Routing profile: truck or auto}
        {--refresh : Recalculate non-manual existing pairs}
        {--include-no-route : Retry pairs previously marked no_route}
        {--dry-run : Validate selection and print the upper-bound pair count without writing or queueing}';

    protected $description = 'Queue a selected or full directed automobile distance matrix';

    public function handle(CityDistanceMatrixService $matrix): int
    {
        $cityIds = $this->cityIds();
        $all = (bool) $this->option('all');
        $profile = (string) $this->option('profile');
        $max = max(2, (int) config('logistics.matrix_max_cities_per_request', 50));

        if ($all && $cityIds !== []) {
            $this->error('Use either --all or --cities, not both.');

            return self::INVALID;
        }

        if (! $all && (count($cityIds) < 2 || count($cityIds) > $max)) {
            $this->error("Select between 2 and {$max} unique city IDs with --cities.");

            return self::INVALID;
        }

        if (! in_array($profile, ['truck', 'auto'], true)) {
            $this->error('--profile must be truck or auto.');

            return self::INVALID;
        }

        if ($all) {
            $cityIds = $matrix->fullMatrixCityIds();
        } else {
            $validCount = LogisticsCity::query()
                ->whereIn('city_id', $cityIds)
                ->where('is_matrix_enabled', true)
                ->whereNotNull('routing_latitude')
                ->whereNotNull('routing_longitude')
                ->whereNotNull('coordinates_verified_at')
                ->count();

            if ($validCount !== count($cityIds)) {
                $this->error('Every selected city must be enabled for the matrix and have verified routing coordinates.');

                return self::FAILURE;
            }
        }

        if (count($cityIds) < 2) {
            $this->error('At least two enabled cities with verified routing coordinates are required.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d cities, at most %d directed pairs, profile=%s. No rows or jobs were created.',
                count($cityIds),
                count($cityIds) * (count($cityIds) - 1),
                $profile,
            ));

            return self::SUCCESS;
        }

        try {
            $run = $all
                ? $matrix->enqueueFullMatrix(
                    $profile,
                    (bool) $this->option('refresh'),
                    ! (bool) $this->option('include-no-route'),
                    null,
                )
                : $matrix->enqueue(
                    $cityIds,
                    $profile,
                    (bool) $this->option('refresh'),
                    ! (bool) $this->option('include-no-route'),
                    null,
                );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Routing run {$run->id} queued with {$run->total_pairs} directed pairs.");

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
