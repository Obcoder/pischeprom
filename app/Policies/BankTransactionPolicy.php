<?php

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\User;

class BankTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank.view');
    }

    public function view(User $user, BankTransaction $transaction): bool
    {
        return $user->can('bank.view');
    }

    public function reconcile(User $user, BankTransaction $transaction): bool
    {
        return $user->can('bank.reconcile');
    }
}
