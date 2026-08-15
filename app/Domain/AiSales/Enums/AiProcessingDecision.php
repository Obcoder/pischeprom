<?php

namespace App\Domain\AiSales\Enums;

enum AiProcessingDecision: string
{
    case Allow = 'allow';
    case Redact = 'redact';
    case Block = 'block';
    case RequireReview = 'require_review';
}
