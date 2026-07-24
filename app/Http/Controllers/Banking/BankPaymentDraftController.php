<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\BankPaymentDraftService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankPaymentDraftRequest;
use App\Models\BankAccount;
use App\Models\BankPaymentOrderDraft;
use App\Models\Entity;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BankPaymentDraftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BankPaymentOrderDraft::class);
        $drafts = BankPaymentOrderDraft::query()
            ->with([
                'payerAccount:id,masked_number,name,currency',
                'recipientEntity:id,name,INN,KPP',
                'purchase:id,entity_id,date,amount',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json($drafts);
    }

    public function options(Request $request): JsonResponse
    {
        Gate::authorize('bank.manage_payment_drafts');
        Gate::authorize('bank.view_sensitive');
        $validated = $request->validate([
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) $request->query('search', ''));
        $entityColumns = [
            'id',
            'name',
            'full_name',
            'INN',
            'KPP',
            'bank_account_number',
            'bank_name',
            'bank_bic',
            'bank_corr_account',
        ];
        $purchaseColumns = ['id', 'date', 'entity_id', 'amount'];
        $requestedPurchase = isset($validated['purchase_id'])
            ? Purchase::query()
                ->without('goods')
                ->with('entity:id,name,INN,KPP')
                ->findOrFail((int) $validated['purchase_id'], $purchaseColumns)
            : null;
        $entities = Entity::query()
            ->without(['buildings', 'classification', 'country'])
            ->when($search !== '', function ($query) use ($search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('INN', $search));
            })
            ->orderBy('name')
            ->limit(100)
            ->get($entityColumns);
        $requiredEntityIds = collect([
            $validated['entity_id'] ?? null,
            $requestedPurchase?->entity_id,
        ])->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($requiredEntityIds->isNotEmpty()) {
            $requiredEntities = Entity::query()
                ->without(['buildings', 'classification', 'country'])
                ->whereKey($requiredEntityIds)
                ->get($entityColumns);
            $entities = $requiredEntities->concat($entities)->unique('id')->values();
        }

        $purchases = Purchase::query()
            ->without('goods')
            ->with('entity:id,name,INN,KPP')
            ->latest('date')
            ->limit(100)
            ->get($purchaseColumns);

        if ($requestedPurchase) {
            $purchases = collect([$requestedPurchase])
                ->concat($purchases)
                ->unique('id')
                ->values();
        }

        return response()->json([
            'data' => [
                'accounts' => BankAccount::query()
                    ->where('status', 'active')
                    ->with('connection.ownerEntity:id,name,full_name,INN,KPP')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (BankAccount $account): array => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'number' => $account->account_number,
                        'masked_number' => $account->masked_number,
                        'currency' => $account->currency,
                        'bank_name' => data_get($account->normalized_requisites, 'bank_name'),
                        'bic' => data_get($account->normalized_requisites, 'bic'),
                        'corr_account' => data_get($account->normalized_requisites, 'corr_account'),
                        'owner' => $account->connection->ownerEntity,
                    ]),
                'entities' => $entities->map(fn (Entity $entity): array => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'full_name' => $entity->full_name,
                    'INN' => $entity->INN,
                    'KPP' => $entity->KPP,
                    'bank_account_number' => $entity->bank_account_number,
                    'bank_name' => $entity->bank_name,
                    'bank_bic' => $entity->bank_bic,
                    'bank_corr_account' => $entity->bank_corr_account,
                ])->values(),
                'purchases' => $purchases,
            ],
        ]);
    }

    public function store(
        BankPaymentDraftRequest $request,
        BankPaymentDraftService $service,
    ): JsonResponse {
        Gate::authorize('create', BankPaymentOrderDraft::class);

        return response()->json([
            'data' => $service->create($request->validated(), $request->user()),
            'message' => 'Local payment-order draft was created. It was not sent to Sber.',
        ], 201);
    }

    public function update(
        BankPaymentDraftRequest $request,
        BankPaymentOrderDraft $draft,
        BankPaymentDraftService $service,
    ): JsonResponse {
        Gate::authorize('update', $draft);

        return response()->json([
            'data' => $service->update($draft, $request->validated(), $request->user()),
            'message' => 'Local draft was updated. It was not sent to Sber.',
        ]);
    }

    public function export(
        Request $request,
        BankPaymentOrderDraft $draft,
        BankPaymentDraftService $service,
    ): JsonResponse {
        Gate::authorize('export', $draft);
        $draft = $service->markExported($draft, $request->user());

        return response()->json([
            'data' => $draft,
            'print_url' => route('admin.bank.drafts.print', $draft),
            'message' => 'Local print export prepared. It was not sent to Sber.',
        ]);
    }

    public function cancel(
        Request $request,
        BankPaymentOrderDraft $draft,
        BankPaymentDraftService $service,
    ): JsonResponse {
        Gate::authorize('update', $draft);

        return response()->json([
            'data' => $service->cancel($draft, $request->user()),
            'message' => 'Local draft was cancelled.',
        ]);
    }

    public function print(BankPaymentOrderDraft $draft): View
    {
        Gate::authorize('view', $draft);
        $draft->load(['payerAccount', 'recipientEntity', 'purchase', 'createdBy']);

        return view('banking.payment-draft-print', [
            'draft' => $draft,
            'warning' => 'Это локальный черновик. Он не отправлен в Сбер, не подписан и не является исполненным платёжным поручением.',
        ]);
    }
}
