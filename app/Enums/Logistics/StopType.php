<?php

namespace App\Enums\Logistics;

enum StopType: string
{
    case Origin = 'origin';
    case Waypoint = 'waypoint';
    case Destination = 'destination';
}
