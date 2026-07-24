<?php

namespace App\Policies;

use App\Models\BankAuditEvent;
use App\Models\User;

class BankAuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank.view_audit');
    }

    public function view(User $user, BankAuditEvent $event): bool
    {
        return $user->can('bank.view_audit');
    }

    public function update(User $user, BankAuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, BankAuditEvent $event): bool
    {
        return false;
    }
}
