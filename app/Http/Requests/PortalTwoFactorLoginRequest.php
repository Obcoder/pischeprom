<?php

namespace App\Http\Requests;

use App\Services\Auth\StaffAccess;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class PortalTwoFactorLoginRequest extends TwoFactorLoginRequest
{
    public function challengedUser()
    {
        $user = parent::challengedUser();

        // Access may be revoked between the password and second-factor steps.
        if ($this->session()->get('auth.login_portal') === 'ameise'
            && ! app(StaffAccess::class)->allows($user)) {
            $this->session()->forget(['auth.login_portal', 'login.id', 'login.remember']);

            throw ValidationException::withMessages(['code' => [trans('auth.failed')]]);
        }

        return $user;
    }
}
