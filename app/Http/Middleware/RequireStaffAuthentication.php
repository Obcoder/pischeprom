<?php

namespace App\Http\Middleware;

use App\Services\Auth\StaffAccess;
use App\Services\Auth\StaffRouteAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffAuthentication
{
    public function __construct(
        private readonly StaffAccess $access,
        private readonly StaffRouteAccess $routes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->route() || ! $this->routes->requiresStaff($request->route(), $request->method())) {
            return $next($request);
        }

        $json = $request->is('api/*') || $request->expectsJson();
        $user = $request->user();
        if (! $user) {
            $user = Auth::guard('sanctum')->user();
            if ($user) {
                Auth::shouldUse('sanctum');
            }
        }

        if (! $user) {
            return $json
                ? response()->json(['message' => 'Unauthenticated.'], 401, ['Cache-Control' => 'no-store, private'])
                : redirect()->guest(route('Ameise.login'));
        }

        if (! $this->access->allows($user)) {
            if ($json) {
                return response()->json(['message' => 'Доступ разрешён только сотрудникам.'], 403, ['Cache-Control' => 'no-store, private']);
            }

            abort(403, 'Доступ разрешён только сотрудникам.');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
