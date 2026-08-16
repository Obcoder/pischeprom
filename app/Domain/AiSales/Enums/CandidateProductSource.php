<?php

namespace App\Domain\AiSales\Enums;

enum CandidateProductSource: string
{
    case Job = 'job';
    case ManualReview = 'manual_review';
    case Rule = 'rule';
    case FutureAi = 'future_ai';
}
