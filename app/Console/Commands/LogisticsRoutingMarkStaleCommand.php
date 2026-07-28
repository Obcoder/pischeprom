<?php

namespace App\Console\Commands;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RouteStatus;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsTripRoute;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LogisticsRoutingMarkStaleCommand extends Command
{
    protected $signature = 'logistics:routing-mark-stale
        {--old-osm-version= : Only records calculated with this OSM snapshot}
        {--expired-only : Only matrix rows whose expires_at is in the past}
        {--dry-run : Count records without changing them}';

    protected $description = 'Mark calculated route and matrix cache records stale after data/profile refreshes';

    public function handle(): int
    {
        $oldVersion = trim((string) $this->option('old-osm-version'));
        $expiredOnly = (bool) $this->option('expired-only');
        $dryRun = (bool) $this->option('dry-run');

        $matrix = LogisticsCityDistance::query()
            ->where('status', DistanceStatus::Calculated->value)
            ->when($oldVersion !== '', fn (Builder $query) => $query->where('osm_data_version', $oldVersion))
            ->when($expiredOnly, fn (Builder $query) => $query->where('expires_at', '<=', now()));
        $routes = LogisticsTripRoute::query()
            ->where('status', RouteStatus::Calculated->value)
            ->when($oldVersion !== '', fn (Builder $query) => $query->where('osm_data_version', $oldVersion));
        $matrixCount = (clone $matrix)->count();
        $routeCount = $expiredOnly ? 0 : (clone $routes)->count();

        if (! $dryRun) {
            DB::transaction(function () use ($matrix, $routes, $expiredOnly): void {
                $matrix->update(['status' => DistanceStatus::Stale->value]);
                if (! $expiredOnly) {
                    $routes->update(['status' => RouteStatus::Stale->value]);
                }
            }, 3);
        }

        $prefix = $dryRun ? 'Dry run' : 'Updated';
        $this->info("{$prefix}: matrix={$matrixCount}, trip_routes={$routeCount}.");

        return self::SUCCESS;
    }
}
