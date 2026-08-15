<?php

namespace App\Domain\AiSales\Enums;

enum AiRunStepStatus: string
{
    case Queued = 'queued';
    case Ready = 'ready';
    case Sent = 'sent';
    case Processing = 'processing';
    case RequiresAction = 'requires_action';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';
}
