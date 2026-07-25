<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderPageController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Ameise/Orders/Index', [
            'permissions' => $this->permissions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Ameise/Orders/Show', [
            'orderId' => null,
            'permissions' => $this->permissions($request),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        return Inertia::render('Ameise/Orders/Show', [
            'orderId' => $order->id,
            'permissions' => $this->permissions($request),
        ]);
    }

    private function permissions(Request $request): array
    {
        return [
            'view' => $request->user()?->can('orders.view') ?? false,
            'create' => $request->user()?->can('orders.create') ?? false,
            'edit' => $request->user()?->can('orders.edit') ?? false,
            'delete' => $request->user()?->can('orders.delete') ?? false,
        ];
    }
}
