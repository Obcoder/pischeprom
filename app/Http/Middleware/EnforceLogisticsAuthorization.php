<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Response;

class EnforceLogisticsAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('logistics.authorization_enabled')) {
            return $next($request);
        }

        return app(Pipeline::class)
            ->send($request)
            ->through([
                Authenticate::using('sanctum'),
                EnsureEmailIsVerified::class,
                Authorize::using('logistics.view'),
            ])
            ->then(fn (Request $request): Response => $next($request));
    }
}
