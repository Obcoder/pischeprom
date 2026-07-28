<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreLogisticsTripRequest;
use App\Http\Requests\Logistics\UpdateLogisticsTripRequest;
use App\Http\Resources\Logistics\LogisticsTripResource;
use App\Models\LogisticsTrip;
use App\Services\Logistics\TripWriterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(private readonly TripWriterService $writer) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeLogistics('viewAny', LogisticsTrip::class);
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $query = LogisticsTrip::query()
            ->with([
                'vehicle.owner:id,name',
                'carrier:id,name',
                'responsible:id,name',
                'stops.city.region',
                'expenses.category',
                'currentRoute',
            ])
            ->withCount(['stops', 'expenses'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function (Builder $query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('cargo_description', 'like', "%{$search}%")
                        ->orWhereHas('vehicle', fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('registration_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->filled('vehicle_id'), fn (Builder $query) => $query->where('vehicle_id', $request->input('vehicle_id')))
            ->when($request->filled('carrier_entity_id'), fn (Builder $query) => $query->where('carrier_entity_id', $request->input('carrier_entity_id')))
            ->when($request->filled('city_id'), fn (Builder $query) => $query->whereHas('stops', fn (Builder $q) => $q->where('city_id', $request->input('city_id'))))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->where('planned_departure_at', '>=', $request->date('date_from')?->startOfDay()))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->where('planned_departure_at', '<=', $request->date('date_to')?->endOfDay()))
            ->when($request->input('has_route') === '0', fn (Builder $query) => $query->whereNull('route_calculated_at'))
            ->when($request->input('has_route') === '1', fn (Builder $query) => $query->whereNotNull('route_calculated_at'))
            ->when($request->boolean('expenses_without_check'), fn (Builder $query) => $query->whereHas('expenses', fn (Builder $q) => $q->whereNull('check_id')));

        $this->applySort($query, $request);
        $trips = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => LogisticsTripResource::collection($trips->items())->resolve($request),
            'meta' => [
                'current_page' => $trips->currentPage(),
                'last_page' => $trips->lastPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
            ],
        ]);
    }

    public function store(StoreLogisticsTripRequest $request): JsonResponse
    {
        $trip = $this->writer->create($request->validated(), $request->user()?->id);

        return (new LogisticsTripResource($trip))->response()->setStatusCode(201);
    }

    public function show(LogisticsTrip $trip): LogisticsTripResource
    {
        $this->authorizeLogistics('view', $trip);

        return new LogisticsTripResource($this->writer->load($trip));
    }

    public function update(UpdateLogisticsTripRequest $request, LogisticsTrip $trip): LogisticsTripResource
    {
        return new LogisticsTripResource(
            $this->writer->update($trip, $request->validated(), $request->user()?->id)
        );
    }

    public function destroy(LogisticsTrip $trip): JsonResponse
    {
        $this->authorizeLogistics('delete', $trip);
        $trip->delete();

        return response()->json(['message' => 'Рейс перемещён в архив. Чеки сохранены.']);
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = in_array($request->input('sort_by'), [
            'number', 'status', 'planned_departure_at', 'actual_departure_at',
            'cargo_weight_kg', 'planned_distance_m', 'actual_distance_m', 'created_at',
        ], true) ? $request->input('sort_by') : 'planned_departure_at';
        $direction = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderByRaw($sort.' IS NULL')->orderBy($sort, $direction)->orderByDesc('id');
    }
}
