<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Models\OutreachDispatch;
use App\Models\User;

class OutreachDispatchPolicy
{
    public function __construct(private readonly OutreachAuthorizationService $authorization) {}

    public function view(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW_DISPATCH, $dispatch->businessContext);
    }

    public function queue(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::QUEUE_DISPATCH, $dispatch->businessContext);
    }

    public function cancel(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::CANCEL_DISPATCH, $dispatch->businessContext);
    }

    public function viewEvents(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW_EVENTS, $dispatch->businessContext);
    }

    public function viewReplies(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW_REPLIES, $dispatch->businessContext);
    }

    public function manageFollowups(User $user, OutreachDispatch $dispatch): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::MANAGE_FOLLOWUPS, $dispatch->businessContext);
    }
}
