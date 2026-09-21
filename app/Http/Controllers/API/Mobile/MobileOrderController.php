<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Orders\MobileOrderPresenter;
use App\Services\Orders\OrderFulfillmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileOrderController extends Controller
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
        private readonly MobileOrderPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'filter' => ['nullable', Rule::in(['all', 'today', 'awaiting', 'ready', 'shipped'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim($data['search'] ?? '');
        $query = Order::query()->with($this->fulfillment->relations())
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', '%'.$search.'%'));
            }));

        if (in_array($data['filter'] ?? 'all', ['awaiting', 'ready'], true)) {
            $query->whereNull('closed_at')->whereHas('status', fn (Builder $status) => $status
                ->where('code', OrderStatus::OPEN)->where('is_closed', false));
        }

        match ($data['filter'] ?? 'all') {
            'today' => $query->whereDate('submitted_at', now()->toDateString()),
            'awaiting' => $query->whereNull('shipped_sale_id')->whereNull('prepared_at'),
            'ready' => $query->whereNull('shipped_sale_id')->whereNotNull('prepared_at'),
            'shipped' => $query->whereNotNull('shipped_sale_id'),
            default => null,
        };
        $orders = $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate($data['per_page'] ?? 20);

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
