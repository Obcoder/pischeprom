<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderDeliveryAccess;
use Inertia\Inertia;
use Inertia\Response;

class OrderPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Ameise/Orders/Index', [
            'permissions' => $this->permissions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Ameise/Orders/Show', [
            'orderId' => null,
            'permissions' => $this->permissions(),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('Ameise/Orders/Show', [
            'orderId' => $order->id,
            'permissions' => $this->permissions(),
        ]);
    }

    private function permissions(): array
    {
        return [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delivery_edit' => OrderDeliveryAccess::allowed(request()->user()),
            'delivery_create' => OrderDeliveryAccess::allowed(request()->user(), 'orders.create'),
            'delete' => true,
        ];
    }
}
