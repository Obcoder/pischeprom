<?php

namespace App\Domain\Banking\Enums;

enum ReconciliationStatus: string
{
    case Unmatched = 'unmatched';
    case Suggested = 'suggested';
    case PartiallyAllocated = 'partially_allocated';
    case Allocated = 'allocated';
    case Overpaid = 'overpaid';
    case NotRequired = 'not_required';
    case NeedsReview = 'needs_review';
}
