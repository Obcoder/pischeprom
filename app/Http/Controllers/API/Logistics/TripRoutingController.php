<?php

namespace App\Http\Controllers\API\Logistics;

use App\Enums\Logistics\RoutingRunStatus;
use App\Enums\Logistics\RoutingRunType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\RoutingRunResource;
use App\Http\Resources\Logistics\TripRouteResource;
use App\Http\Resources\Logistics\TripRouteSummaryResource;
use App\Jobs\Logistics\CalculateTripRouteJob;
use App\Models\LogisticsRoutingRun;
use App\Models\LogisticsTrip;
use App\Services\Logistics\Routing\Exceptions\RoutingQueueUnavailableException;
use App\Services\Logistics\RoutingRunService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TripRoutingController extends Controller
{
    public function __construct(private readonly RoutingRunService $runs) {}

    public function index(Request $request, LogisticsTrip $trip): AnonymousResourceCollection
    {
        $this->authorizeLogistics('view', $trip);
        $perPage = max(1, min($request->integer('per_page', 25), 100));

        $query = $trip->routes()->with('creator:id,name');
        if ($request->boolean('summary')) {
            $query->select([
                'id', 'trip_id', 'is_current', 'status', 'routing_profile',
                'vehicle_profile_hash', 'request_hash', 'distance_m', 'duration_s',
                'routing_options', 'provider', 'routing_engine_version',
                'osm_data_version', 'calculated_at', 'created_by', 'created_at',
            ])->selectRaw("CASE WHEN shape_polyline6 IS NOT NULL AND shape_polyline6 <> '' THEN 1 ELSE 0 END AS geometry_available");
        }
        $routes = $query->paginate($perPage);

        return $request->boolean('summary')
            ? TripRouteSummaryResource::collection($routes)
            : TripRouteResource::collection($routes);
    }

    public function store(Request $request, LogisticsTrip $trip): JsonResponse
    {
        $this->authorizeLogistics('update', $trip);
        $validated = $request->validate(['force' => ['nullable', 'boolean']]);
        $force = (bool) ($validated['force'] ?? false);

        try {
            $cache = config('logistics.lock_store')
                ? Cache::store((string) config('logistics.lock_store'))
                : Cache::getFacadeRoot();
            $run = $cache->lock("logistics:queue-trip-route:{$trip->id}", 10)
                ->block(3, function () use ($trip, $request, $force): LogisticsRoutingRun {
                    $active = LogisticsRoutingRun::query()
                        ->where('operation_type', RoutingRunType::TripRoute->value)
                        ->whereIn('status', [
                            RoutingRunStatus::Queued->value,
                            RoutingRunStatus::Running->value,
                        ])
                        ->where('parameters->trip_id', $trip->id)
                        ->latest()
                        ->first();

                    if ($active) {
                        return $active;
                    }

                    $run = $this->runs->create(
                        RoutingRunType::TripRoute,
                        $trip->routing_profile ?: (string) config('logistics.default_routing_profile', 'truck'),
                        1,
                        $request->user()?->id,
                        ['trip_id' => $trip->id, 'force' => $force],
                    );

                    try {
                        CalculateTripRouteJob::dispatch(
                            $trip->id,
                            $run->id,
                            $request->user()?->id,
                            $force,
                        );
                    } catch (Throwable $exception) {
                        $this->runs->failRun(
                            $run,
                            'Расчёт не поставлен в очередь из-за временной ошибки инфраструктуры.',
                        );
                        report($exception);

                        throw new RoutingQueueUnavailableException(previous: $exception);
                    }

                    return $run;
                });
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Маршрут этого рейса уже ставится в очередь. Повторите запрос через несколько секунд.',
                'code' => 'route_queue_locked',
            ], 409);
        } catch (RoutingQueueUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new RoutingQueueUnavailableException(previous: $exception);
        }

        return (new RoutingRunResource($run->refresh()))
            ->response()
            ->setStatusCode(202);
    }
}
