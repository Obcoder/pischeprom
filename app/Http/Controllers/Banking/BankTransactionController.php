<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\BankTransactionPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankTransactionIndexRequest;
use App\Models\BankAuditEvent;
use App\Models\BankTransaction;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BankTransactionController extends Controller
{
    public function index(
        BankTransactionIndexRequest $request,
        BankTransactionPresenter $presenter,
    ): JsonResponse {
        $filters = $request->validated();
        $query = BankTransaction::query()
            ->with(['account', 'entity'])
            ->withSum('activeAllocations as allocated_amount', 'amount');

        $this->applyFilters($query, $filters);
        $sortBy = $filters['sort_by'] ?? 'operation_date';
        $sortDirection = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection)->orderBy('id', $sortDirection);
        $paginator = $query->paginate($filters['per_page'] ?? 25);
        $sensitive = Gate::allows('bank.view_sensitive');
        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (BankTransaction $transaction): array => $presenter->summary($transaction, $sensitive))
        );

        return response()->json($paginator);
    }

    public function show(
        BankTransaction $transaction,
        BankTransactionPresenter $presenter,
    ): JsonResponse {
        Gate::authorize('view', $transaction);
        $transaction->load([
            'account',
            'entity',
            'allocations.allocatable.entity',
            'allocations.confirmedBy:id,name',
            'suggestions.suggestable.entity',
        ])->loadSum('activeAllocations as allocated_amount', 'amount');
        $audit = Gate::allows('bank.view_audit')
            ? BankAuditEvent::query()
                ->where('auditable_type', $transaction->getMorphClass())
                ->where('auditable_id', $transaction->id)
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (BankAuditEvent $event): array => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'correlation_id' => $event->correlation_id,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at?->toISOString(),
                ])
            : collect();

        return response()->json([
            'data' => [
                ...$presenter->detail($transaction, Gate::allows('bank.view_sensitive')),
                'audit' => $audit,
            ],
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $value) => $q->whereDate('operation_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $value) => $q->whereDate('operation_date', '<=', $value))
            ->when($filters['account_id'] ?? null, fn (Builder $q, int $value) => $q->where('bank_account_id', $value))
            ->when($filters['direction'] ?? null, fn (Builder $q, string $value) => $q->where('direction', $value))
            ->when($filters['amount_min'] ?? null, fn (Builder $q, string $value) => $q->where('amount', '>=', str_replace(',', '.', $value)))
            ->when($filters['amount_max'] ?? null, fn (Builder $q, string $value) => $q->where('amount', '<=', str_replace(',', '.', $value)))
            ->when($filters['inn'] ?? null, function (Builder $q, string $value): void {
                $q->where(fn (Builder $nested) => $nested
                    ->where('payer_inn', $value)
                    ->orWhere('recipient_inn', $value));
            })
            ->when($filters['purpose'] ?? null, function (Builder $q, string $value): void {
                $q->where('purpose', 'like', '%'.addcslashes($value, '%_\\').'%');
            })
            ->when($filters['entity'] ?? null, function (Builder $q, string $value): void {
                $escaped = addcslashes($value, '%_\\');
                $q->where(function (Builder $nested) use ($escaped): void {
                    $nested->whereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', "%{$escaped}%"))
                        ->orWhere('payer_name', 'like', "%{$escaped}%")
                        ->orWhere('recipient_name', 'like', "%{$escaped}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['reconciliation_status'] ?? null, fn (Builder $q, string $value) => $q->where('reconciliation_status', $value))
            ->when($filters['worklist'] ?? null, function (Builder $q, string $worklist): void {
                if ($worklist === 'linked') {
                    $q->whereHas('activeAllocations');

                    return;
                }

                $q->where(function (Builder $nested): void {
                    $nested
                        ->whereIn('reconciliation_status', ['partially_allocated', 'overpaid'])
                        ->orWhereHas('activeAllocations', function (Builder $allocations): void {
                            $allocations
                                ->where('allocatable_type', (new Sale)->getMorphClass())
                                ->whereIn(
                                    'allocatable_id',
                                    Sale::query()
                                        ->select('id')
                                        ->where('payment_status', 'partially_paid')
                                );
                        });
                });
            })
            ->when(
                filter_var($filters['warning'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn (Builder $q) => $q->whereNotNull('review_reason')
            );
    }
}
