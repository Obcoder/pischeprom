<?php

namespace App\Enums\Logistics;

enum RouteStatus: string
{
    case Pending = 'pending';
    case Calculated = 'calculated';
    case Stale = 'stale';
    case NoRoute = 'no_route';
    case Failed = 'failed';
}
