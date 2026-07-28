<?php

namespace App\Enums\Logistics;

enum DistanceStatus: string
{
    case Pending = 'pending';
    case Calculated = 'calculated';
    case Manual = 'manual';
    case Stale = 'stale';
    case NoRoute = 'no_route';
    case Failed = 'failed';
}
