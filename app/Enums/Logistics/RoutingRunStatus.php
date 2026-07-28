<?php

namespace App\Enums\Logistics;

enum RoutingRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
