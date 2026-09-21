<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class MobileAccess
{
    public const ABILITY = 'mobile:orders';

    public static function allowed(User $user): bool
    {
        if ($user->status !== 'active' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        // Preserve the existing CRM administrator policy, including legacy accounts.
        if ($user->hasRole('admin', 'crm')) {
            return true;
        }

        try {
            return $user->type === 'employee' && $user->hasPermissionTo('warehouse.move', 'crm');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public static function token(Request $request): ?PersonalAccessToken
    {
        $bearer = $request->bearerToken();

        // Include Sanctum's secret-only representation so it cannot escape scope checks.
        if (! is_string($bearer) || strlen($bearer) > 512
            || ! preg_match('/^(?:[0-9]{1,20}\|)?[^|\s]+$/D', $bearer)) {
            return null;
        }

        return Sanctum::personalAccessTokenModel()::findToken($bearer);
    }

    public static function isMobileToken(PersonalAccessToken $token): bool
    {
        return str_starts_with($token->name, 'mobile:')
            || in_array(self::ABILITY, $token->abilities ?? [], true);
    }
}
