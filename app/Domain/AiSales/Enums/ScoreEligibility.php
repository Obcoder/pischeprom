<?php

namespace App\Domain\AiSales\Enums;

enum ScoreEligibility: string
{
    case NotEvaluated = 'not_evaluated';
    case ResearchOnly = 'research_only';
    case ReviewRequired = 'review_required';
    case BlockedDoNotContact = 'blocked_do_not_contact';
    case BlockedSuppressed = 'blocked_suppressed';
    case BlockedPolicy = 'blocked_policy';
    case BlockedDuplicate = 'blocked_duplicate';

    public function blocked(): bool
    {
        return str_starts_with($this->value, 'blocked_');
    }
}
