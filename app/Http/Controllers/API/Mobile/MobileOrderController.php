<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\MobileOrderPresenter;
use App\Services\Orders\MobileOrderQuery;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileOrderController extends Controller
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
        private readonly MobileOrderPresenter $presenter,
        private readonly MobileOrderQuery $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->orders->validated($request);
        $orders = $this->orders->build($data)->with($this->fulfillment->relations())
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $this->presenter->many($orders->getCollection()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['data' => $this->presenter->one($order)]);
    }

    public function prepare(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.measure_id' => ['required', 'integer', 'exists:measures,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);
        $order = $this->fulfillment->prepare($order, $data, $request->user());

        return response()->json(['data' => $this->presenter->one($order)]);
    }

    public function ship(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'request_id' => ['required', 'uuid'],
        ]);
        $order = $this->fulfillment->ship($order, $data, $request->user());

        return response()->json(['data' => $this->presenter->one($order)]);
    }
}
