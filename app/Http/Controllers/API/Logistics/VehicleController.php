<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreVehicleRequest;
use App\Http\Requests\Logistics\UpdateVehicleRequest;
use App\Http\Resources\Logistics\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $query = Vehicle::query()
            ->with('owner:id,name')
            ->withCount('trips')
            ->when($request->boolean('with_archived'), fn (Builder $query) => $query->withTrashed())
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $registration = Vehicle::normalizeRegistrationNumber($search);
                $query->where(function (Builder $query) use ($search, $registration) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$registration}%")
                        ->orWhere('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$registration}%");
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('vehicle_type'), fn (Builder $query) => $query->where('vehicle_type', $request->input('vehicle_type')))
            ->when($request->filled('owner_entity_id'), fn (Builder $query) => $query->where('owner_entity_id', $request->input('owner_entity_id')))
            ->when($request->has('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')));

        $this->applySort($query, $request);
        $vehicles = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => VehicleResource::collection($vehicles->items())->resolve($request),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
            ],
        ]);
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ])->load('owner')->loadCount('trips');

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle->load('owner')->loadCount('trips'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $vehicle->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleResource($vehicle->refresh()->load('owner')->loadCount('trips'));
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('delete', $vehicle);
        $vehicle->delete();

        return response()->json(['message' => 'Автомобиль перемещён в архив.']);
    }

    public function restore(Request $request, int $vehicle): VehicleResource
    {
        $model = Vehicle::withTrashed()->findOrFail($vehicle);
        Gate::authorize('restore', $model);
        $model->restore();

        return new VehicleResource($model->refresh()->load('owner')->loadCount('trips'));
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = in_array($request->input('sort_by'), [
            'name', 'registration_number', 'make', 'year', 'status', 'payload_capacity_kg', 'created_at',
        ], true) ? $request->input('sort_by') : 'name';
        $direction = strtolower((string) $request->input('sort_direction')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction)->orderBy('id');
    }
}
