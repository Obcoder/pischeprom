<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\UpsertLogisticsCityRequest;
use App\Http\Resources\Logistics\LogisticsCityResource;
use App\Models\City;
use App\Models\LogisticsCity;
use App\Services\Logistics\LogisticsCityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LogisticsCityController extends Controller
{
    public function __construct(private readonly LogisticsCityService $cities) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LogisticsCity::class);
        $perPage = max(1, min((int) $request->input('per_page', 50), 200));

        $query = City::query()
            ->with(['region:id,name', 'logisticsSetting.verifier:id,name'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%'))
            ->when($request->boolean('enabled_only'), fn (Builder $query) => $query->whereHas('logisticsSetting'))
            ->when($request->boolean('matrix_only'), fn (Builder $query) => $query->whereHas('logisticsSetting', fn (Builder $q) => $q->where('is_matrix_enabled', true)))
            ->when($request->boolean('missing_coordinates'), fn (Builder $query) => $query->whereHas('logisticsSetting', fn (Builder $q) => $q
                ->whereNull('routing_latitude')->orWhereNull('routing_longitude')))
            ->orderBy('name');

        $cities = $query->paginate($perPage);

        return response()->json([
            'data' => LogisticsCityResource::collection($cities->items())->resolve($request),
            'meta' => [
                'current_page' => $cities->currentPage(),
                'last_page' => $cities->lastPage(),
                'total' => $cities->total(),
                'matrix_ready_total' => LogisticsCity::query()
                    ->where('is_matrix_enabled', true)
                    ->whereNotNull('routing_latitude')
                    ->whereNotNull('routing_longitude')
                    ->whereNotNull('coordinates_verified_at')
                    ->count(),
            ],
        ]);
    }

    public function update(UpsertLogisticsCityRequest $request, City $city): LogisticsCityResource
    {
        $this->cities->upsert($city, $request->validated(), $request->user()?->id);

        return new LogisticsCityResource($city->refresh()->load(['region', 'logisticsSetting.verifier']));
    }
}
