<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureBankConnectionAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        try {
            $isAdministrator = $user !== null
                && method_exists($user, 'hasRole')
                && $user->hasRole('admin', 'crm');
        } catch (Throwable) {
            $isAdministrator = false;
        }

        abort_unless($isAdministrator, 403);

        return $next($request);
    }
}
