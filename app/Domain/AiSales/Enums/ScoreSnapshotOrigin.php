<?php

namespace App\Domain\AiSales\Enums;

enum ScoreSnapshotOrigin: string
{
    case Deterministic = 'deterministic';
    case ManualOverride = 'manual_override';
    case ReviewCorrection = 'review_correction';
    case FutureAiEvidence = 'future_ai_evidence';
}
