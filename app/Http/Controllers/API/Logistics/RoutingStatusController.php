<?php

namespace App\Http\Controllers\API\Logistics;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RouteStatus;
use App\Enums\Logistics\RoutingRunStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsRoutingRun;
use App\Models\LogisticsTripRoute;
use App\Services\Logistics\Map\GisReleaseMetadataService;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RoutingStatusController extends Controller
{
    public function __construct(private readonly GisReleaseMetadataService $gisMetadata) {}

    public function __invoke(RoutingProviderInterface $provider): JsonResponse
    {
        $this->authorizeLogistics('logistics.technical.view');

        $health = $provider->health();
        $gis = $this->gisMetadata->diagnostics();
        $releaseReady = ($gis['status'] ?? null) === 'active';
        $rangeHealthy = (bool) data_get($gis, 'range_requests.healthy', false);
        $overallStatus = match (true) {
            $health->healthy && $releaseReady && $rangeHealthy => 'healthy',
            $health->healthy || $releaseReady => 'degraded',
            default => 'unavailable',
        };
        $healthcheckAt = now()->toISOString();
        $lastSuccessfulHealthcheckAt = $this->lastSuccessfulHealthcheckAt(
            $health->healthy,
            $healthcheckAt,
        );
        $lastMatrixSuccessAt = LogisticsCityDistance::query()
            ->whereIn('status', [DistanceStatus::Calculated->value, DistanceStatus::Manual->value])
            ->max('calculated_at');

        return response()->json([
            'data' => [
                ...$health->toArray(),
                'last_healthcheck_at' => $healthcheckAt,
                'last_successful_healthcheck_at' => $lastSuccessfulHealthcheckAt,
                'overall_status' => $overallStatus,
                'gis' => $gis,
                'queue' => [
                    'connection' => config('logistics.queue_connection') ?: config('queue.default'),
                    'name' => config('logistics.queue'),
                ],
                'matrix' => [
                    'enabled_cities' => LogisticsCity::query()->where('is_matrix_enabled', true)->count(),
                    'verified_cities' => LogisticsCity::query()->whereNotNull('coordinates_verified_at')->count(),
                    'calculated' => LogisticsCityDistance::query()->whereIn('status', [
                        DistanceStatus::Calculated->value,
                        DistanceStatus::Manual->value,
                    ])->count(),
                    'pending' => LogisticsCityDistance::query()->where('status', DistanceStatus::Pending->value)->count(),
                    'stale' => LogisticsCityDistance::query()->where('status', DistanceStatus::Stale->value)->count(),
                    'failed' => LogisticsCityDistance::query()->whereIn('status', [
                        DistanceStatus::Failed->value,
                        DistanceStatus::NoRoute->value,
                    ])->count(),
                    'last_success_at' => $lastMatrixSuccessAt
                        ? Carbon::parse($lastMatrixSuccessAt)->toISOString()
                        : null,
                ],
                'routes' => [
                    'stale' => LogisticsTripRoute::query()->where('status', RouteStatus::Stale->value)->count(),
                    'failed' => LogisticsTripRoute::query()->whereIn('status', [
                        RouteStatus::Failed->value,
                        RouteStatus::NoRoute->value,
                    ])->count(),
                ],
                'runs' => [
                    'active' => LogisticsRoutingRun::query()->whereIn('status', [
                        RoutingRunStatus::Queued->value,
                        RoutingRunStatus::Running->value,
                    ])->count(),
                    'failed_last_24h' => LogisticsRoutingRun::query()
                        ->whereIn('status', [RoutingRunStatus::Failed->value, RoutingRunStatus::Partial->value])
                        ->where('created_at', '>=', now()->subDay())
                        ->count(),
                ],
            ],
        ], $health->healthy ? 200 : 503);
    }

    private function lastSuccessfulHealthcheckAt(bool $healthy, string $checkedAt): ?string
    {
        $key = 'logistics:routing:last-successful-healthcheck-at';

        try {
            if ($healthy) {
                Cache::forever($key, $checkedAt);
            }

            $value = Cache::get($key);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return $healthy ? $checkedAt : null;
        }
    }
}
