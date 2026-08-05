<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Response;

class EnforceAiPriceListAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai-price-lists.authorization_enabled')) {
            return $next($request);
        }

        return app(Pipeline::class)
            ->send($request)
            ->through([
                Authenticate::using('sanctum'),
            ])
            ->then(fn (Request $request): Response => $next($request));
    }
}
