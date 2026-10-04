<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Http\Resources\OrderResource;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Orders\OrderWriter;
use Inertia\Inertia;
use Inertia\Response;

class Verwalter extends Controller
{
    public function __construct(private readonly OrderWriter $writer) {}

    public function index(): Response
    {
        $activeLeads = Lead::query()
            ->with(['mailMessage:id', 'telephone', 'entity', 'unit'])
            ->open()
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();
        $orderStatuses = OrderStatus::query()
            ->ordered()
            ->get(['id', 'code', 'name', 'color', 'is_closed']);

        return Inertia::render('Ameise/Verwalter', [
            'activeLeads' => LeadResource::collection($activeLeads)->resolve(),
            'canViewOrders' => true,
            'orderStatuses' => $orderStatuses,
            'ordersByStatus' => $orderStatuses
                ->reject(fn (OrderStatus $status) => $status->is_closed)
                ->mapWithKeys(fn (OrderStatus $status) => [$status->code => $this->ordersForStatus($status->code)]),
        ]);
    }

    private function ordersForStatus(string $status): array
    {
        $orders = Order::query()
            ->with($this->writer->relations())
            ->withCount('items')
            ->whereHas('status', fn ($query) => $query->where('code', $status))
            ->latest('submitted_at')
            ->latest('id')
            ->limit(30)
            ->get();

        return OrderResource::collection($orders)->resolve();
    }
}
