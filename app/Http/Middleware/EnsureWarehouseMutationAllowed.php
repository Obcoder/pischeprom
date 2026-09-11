<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureWarehouseMutationAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowed = false;

        if ($user && $user->status !== 'blocked') {
            try {
                $allowed = $user->hasRole('admin', 'crm')
                    || ($user->type === 'employee' && $user->hasPermissionTo('warehouse.move', 'crm'));
            } catch (Throwable) {
                $allowed = false;
            }
        }

        abort_unless($allowed, 403, 'Недостаточно прав для изменения складского учёта.');

        return $next($request);
    }
}
