<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreTripExpenseRequest;
use App\Http\Requests\Logistics\UpdateTripExpenseRequest;
use App\Http\Resources\Logistics\TripExpenseResource;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripExpense;
use App\Services\Logistics\TripExpenseService;
use App\Services\Logistics\TripMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TripExpenseController extends Controller
{
    public function __construct(
        private readonly TripExpenseService $expenses,
        private readonly TripMetricsService $metrics,
    ) {}

    public function index(Request $request, LogisticsTrip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);
        $items = $trip->expenses()->with(['category', 'check.entity'])->latest('occurred_at')->latest('id')->get();
        $trip->setRelation('expenses', $items)->loadMissing('vehicle');

        return response()->json([
            'data' => TripExpenseResource::collection($items)->resolve($request),
            'metrics' => $this->metrics->calculate($trip),
        ]);
    }

    public function store(StoreTripExpenseRequest $request, LogisticsTrip $trip): JsonResponse
    {
        $expense = $this->expenses->create($trip, $request->validated(), $request->user()?->id);

        return (new TripExpenseResource($expense))->response()->setStatusCode(201);
    }

    public function update(
        UpdateTripExpenseRequest $request,
        LogisticsTrip $trip,
        LogisticsTripExpense $expense,
    ): TripExpenseResource {
        return new TripExpenseResource(
            $this->expenses->update($trip, $expense, $request->validated(), $request->user()?->id)
        );
    }

    public function destroy(LogisticsTrip $trip, LogisticsTripExpense $expense): JsonResponse
    {
        if ($expense->trip_id !== $trip->id) {
            abort(404);
        }

        Gate::authorize('delete', $expense);
        $expense->delete();

        return response()->json(['message' => 'Расход отвязан. Чек не изменён.']);
    }
}
