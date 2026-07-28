<?php

namespace App\Http\Controllers\API\Logistics;

use App\Enums\Logistics\RoutingRunStatus;
use App\Enums\Logistics\RoutingRunType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\RoutingRunResource;
use App\Http\Resources\Logistics\TripRouteResource;
use App\Jobs\Logistics\CalculateTripRouteJob;
use App\Models\LogisticsRoutingRun;
use App\Models\LogisticsTrip;
use App\Services\Logistics\RoutingRunService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class TripRoutingController extends Controller
{
    public function __construct(private readonly RoutingRunService $runs) {}

    public function index(LogisticsTrip $trip): AnonymousResourceCollection
    {
        Gate::authorize('view', $trip);

        return TripRouteResource::collection(
            $trip->routes()->with('creator:id,name')->paginate(25)
        );
    }

    public function store(Request $request, LogisticsTrip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);
        $validated = $request->validate(['force' => ['nullable', 'boolean']]);
        $force = (bool) ($validated['force'] ?? false);
        $cache = config('logistics.lock_store')
            ? Cache::store((string) config('logistics.lock_store'))
            : Cache::getFacadeRoot();

        try {
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

                    CalculateTripRouteJob::dispatch(
                        $trip->id,
                        $run->id,
                        $request->user()?->id,
                        $force,
                    );

                    return $run;
                });
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Маршрут этого рейса уже ставится в очередь. Повторите запрос через несколько секунд.',
                'code' => 'route_queue_locked',
            ], 409);
        }

        return (new RoutingRunResource($run->refresh()))
            ->response()
            ->setStatusCode(202);
    }
}
