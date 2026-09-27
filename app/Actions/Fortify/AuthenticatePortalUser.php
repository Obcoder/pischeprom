<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Auth\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

class AuthenticatePortalUser
{
    public function __invoke(Request $request): ?User
    {
        $ameise = $request->routeIs('Ameise.login.store');
        $request->session()->put('auth.login_portal', $ameise ? 'ameise' : 'customer');
        $request->session()->forget(['login.id', 'login.remember']);

        $provider = Auth::guard(config('fortify.guard'))->getProvider();
        $credentials = $request->only(Fortify::username(), 'password');
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            return null;
        }

        if ($ameise && ! app(StaffAccess::class)->allows($user)) {
            return null;
        }

        if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
            $provider->rehashPasswordIfRequired($user, $credentials);
        }

        return $user;
    }
}
