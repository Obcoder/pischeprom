<?php

namespace App\Http\Responses;

use App\Services\Auth\StaffRouteAccess;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PortalLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        $ameise = $request->session()->pull('auth.login_portal') === 'ameise';

        if ($ameise || ! $this->hasPublicIntendedDestination($request)) {
            $request->session()->forget('url.intended');
        }

        if ($request->wantsJson()) {
            return $request->routeIs('two-factor.login.store')
                ? response()->json('', 204)
                : response()->json(['two_factor' => false]);
        }

        return $ameise
            ? redirect()->route('Ameise')
            : redirect()->intended(Fortify::redirects('login'));
    }

    private function hasPublicIntendedDestination(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return false;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if ($path === '/Ameise' || str_starts_with((string) $path, '/Ameise/')) {
            return false;
        }

        $host = parse_url($intended, PHP_URL_HOST);
        if ($host !== null && $host !== $request->getHost()) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($intended, 'GET'));

            return ! app(StaffRouteAccess::class)->requiresStaff($route, 'GET');
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }
}
