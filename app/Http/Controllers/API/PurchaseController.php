<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request)
    {
        $purchases = Purchase::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->whereHas('entity', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->string('search') . '%');
                });
            })
            ->orderByDesc('date')
            ->paginate(15);

        return PurchaseResource::collection($purchases);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource(
            $purchase->load(['entity', 'goods'])
        );
    }

    public function store(StorePurchaseRequest $request): PurchaseResource
    {
        $purchase = $this->purchaseService->store($request->validated());

        return new PurchaseResource($purchase);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): PurchaseResource
    {
        $purchase = $this->purchaseService->update($purchase, $request->validated());

        return new PurchaseResource($purchase);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $purchase->delete();

        return response()->json([
                                    'message' => 'Закупка удалена',
                                ]);
    }
}
