<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Http\Resources\OrderResource;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderStatus;
use Inertia\Inertia;
use Inertia\Response;

class Verwalter extends Controller
{
    public function index(): Response
    {
        $activeLeads = Lead::query()
            ->with(['telephone', 'entity', 'unit'])
            ->open()
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Ameise/Verwalter', [
            'activeLeads' => LeadResource::collection($activeLeads)->resolve(),
            'canViewOrders' => true,
            'ordersByStatus' => [
                OrderStatus::OPEN => $this->ordersForStatus(OrderStatus::OPEN),
                OrderStatus::DEFERRED => $this->ordersForStatus(OrderStatus::DEFERRED),
            ],
        ]);
    }

    private function ordersForStatus(string $status): array
    {
        $orders = Order::query()
            ->with([
                'status',
                'entity:id,name',
                'buildings:id,address',
                'items.good:id,name,slug',
            ])
            ->withCount('items')
            ->whereHas('status', fn ($query) => $query->where('code', $status))
            ->latest('submitted_at')
            ->latest('id')
            ->limit(30)
            ->get();

        return OrderResource::collection($orders)->resolve();
    }
}
