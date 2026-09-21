<?php

namespace App\Http\Middleware;

use App\Services\Mobile\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictMobileTokenScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/mobile/v1/*') && $request->bearerToken()) {
            $token = MobileAccess::token($request);

            // Run globally: legacy public API endpoints must also reject a mobile token.
            abort_if($token && MobileAccess::isMobileToken($token), 403,
                'Мобильный токен можно использовать только в мобильном API.');
        }

        return $next($request);
    }
}
