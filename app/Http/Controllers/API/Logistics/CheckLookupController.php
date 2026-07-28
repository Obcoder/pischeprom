<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Services\Logistics\TripExpenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckLookupController extends Controller
{
    public function __construct(private readonly TripExpenseService $expenses) {}

    public function __invoke(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 50));
        $query = Check::query()
            ->with('entity:id,name')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', ctype_digit($search) ? (int) $search : -1)
                        ->orWhereHas('entity', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('date', '<=', $request->input('date_to')))
            ->latest('date')
            ->latest('id');

        $checks = $query->paginate($perPage);

        return response()->json([
            'data' => collect($checks->items())->map(fn (Check $check) => [
                'id' => $check->id,
                'date' => $check->date?->toDateString(),
                'amount' => (float) $check->amount,
                'currency_code' => config('logistics.currency_code', 'RUB'),
                'available_amount' => $this->expenses->availableForCheck($check),
                'entity' => $check->entity ? ['id' => $check->entity->id, 'name' => $check->entity->name] : null,
            ])->values(),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'last_page' => $checks->lastPage(),
                'total' => $checks->total(),
            ],
        ]);
    }
}
