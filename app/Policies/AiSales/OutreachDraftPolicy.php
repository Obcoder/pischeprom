<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Models\OutreachDraft;
use App\Models\User;

class OutreachDraftPolicy
{
    public function __construct(private readonly OutreachAuthorizationService $authorization) {}

    public function view(User $user, OutreachDraft $draft): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::VIEW, $draft->businessContext);
    }

    public function update(User $user, OutreachDraft $draft): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::DRAFT, $draft->businessContext);
    }

    public function review(User $user, OutreachDraft $draft): bool
    {
        return $this->authorization->can($user, OutreachAuthorizationService::REVIEW, $draft->businessContext);
    }
}
