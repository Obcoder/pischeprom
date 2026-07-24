<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\PaymentAllocationService;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankReceivableController extends Controller
{
    public function __invoke(Request $request, PaymentAllocationService $allocations): JsonResponse
    {
        Gate::authorize('bank.reconcile');
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $query = Sale::query()
            ->with('entity:id,name,INN,KPP')
            ->whereNotIn('payment_status', ['paid', 'overpaid'])
            ->when($validated['entity_id'] ?? null, fn (Builder $q, int $id) => $q->where('entity_id', $id))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(function (Builder $nested) use ($search, $escaped): void {
                    if (ctype_digit($search)) {
                        $nested->orWhereKey((int) $search);
                    }

                    $nested->orWhere('payment_reference', 'like', "%{$escaped}%")
                        ->orWhereHas('entity', fn (Builder $entity) => $entity
                            ->where('name', 'like', "%{$escaped}%")
                            ->orWhere('INN', $search));
                });
            })
            ->orderByDesc('date')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'data' => $query->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'number' => $sale->payment_reference ?: (string) $sale->id,
                'date' => $sale->date?->toDateString(),
                'entity' => $sale->entity ? [
                    'id' => $sale->entity->id,
                    'name' => $sale->entity->name,
                    'inn' => $sale->entity->INN,
                    'kpp' => $sale->entity->KPP,
                ] : null,
                'total' => $sale->total,
                'paid_amount' => $sale->paid_amount,
                'outstanding_amount' => $allocations->saleOutstandingAmount($sale),
                'payment_status' => $sale->payment_status,
            ])->values(),
        ]);
    }
}
