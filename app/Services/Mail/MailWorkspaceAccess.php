<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class MailWorkspaceAccess
{
    public function authorize(?User $user): void
    {
        if (! $user || ! $user->hasVerifiedEmail() || $user->status !== 'active') {
            throw new AuthorizationException('Доступ к инструментам почты разрешён только сотрудникам.');
        }

        $allowed = $user->type === 'employee';
        if (! $allowed) {
            try {
                $allowed = $user->hasRole('admin', 'crm') || $user->hasPermissionTo('mail.send', 'crm');
            } catch (Throwable) {
                $allowed = false;
            }
        }

        if (! $allowed) {
            throw new AuthorizationException('Доступ к инструментам почты разрешён только сотрудникам.');
        }
    }
}
