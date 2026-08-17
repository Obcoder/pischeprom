<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Models\OutreachReplyLink;
use App\Models\User;

class OutreachReplyLinkPolicy
{
    public function __construct(private readonly OutreachAuthorizationService $authorization) {}

    public function view(User $user, OutreachReplyLink $reply): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW_REPLIES, $reply->dispatch->businessContext);
    }

    public function review(User $user, OutreachReplyLink $reply): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::REVIEW_REPLIES, $reply->dispatch->businessContext);
    }
}
