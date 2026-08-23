<?php

namespace App\Domain\AiSales\Enums;

enum CandidateProductSource: string
{
    case Job = 'job';
    case ManualReview = 'manual_review';
    case Rule = 'rule';
    case Search = 'search';
    case FutureAi = 'future_ai';
}
