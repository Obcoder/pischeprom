<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authenticate the bearer explicitly: a CRM browser cookie cannot grant access.
        $token = MobileAccess::token($request);
        $expiration = config('sanctum.expiration');

        abort_unless($token
            && str_starts_with($token->name, 'mobile:')
            && $token->abilities === [MobileAccess::ABILITY]
            && $token->expires_at && $token->expires_at->isFuture()
            && (! $expiration || $token->created_at->gt(now()->subMinutes($expiration))),
            401, 'Войдите в мобильное приложение заново.');

        $user = $token->tokenable;
        abort_unless($user instanceof User && MobileAccess::allowed($user), 403,
            'Доступ разрешён подтверждённым сотрудникам с правом складских операций.');

        $user->withAccessToken($token);
        Auth::guard('sanctum')->setUser($user);
        Auth::shouldUse('sanctum');
        $request->setUserResolver(fn () => $user);
        event(new TokenAuthenticated($token));
        $token->forceFill(['last_used_at' => now()])->save();

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
