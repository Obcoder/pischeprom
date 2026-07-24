<?php

namespace App\Policies;

use App\Models\BankPaymentOrderDraft;
use App\Models\User;

class BankPaymentOrderDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canHandleSensitiveDrafts($user);
    }

    public function view(User $user, BankPaymentOrderDraft $draft): bool
    {
        return $this->canHandleSensitiveDrafts($user);
    }

    public function create(User $user): bool
    {
        return $this->canHandleSensitiveDrafts($user);
    }

    public function update(User $user, BankPaymentOrderDraft $draft): bool
    {
        return $this->canHandleSensitiveDrafts($user);
    }

    public function export(User $user, BankPaymentOrderDraft $draft): bool
    {
        return $this->canHandleSensitiveDrafts($user);
    }

    private function canHandleSensitiveDrafts(User $user): bool
    {
        return $user->can('bank.manage_payment_drafts')
            && $user->can('bank.view_sensitive');
    }
}
