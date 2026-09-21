<?php

namespace App\Http\Middleware;

use App\Services\Orders\OrderDeliveryAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrderDeliveryMutationAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(OrderDeliveryAccess::allowed($request->user()), 403, 'Недостаточно прав для планирования доставки заказов.');

        return $next($request);
    }
}
