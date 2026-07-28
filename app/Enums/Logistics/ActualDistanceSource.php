<?php

namespace App\Enums\Logistics;

enum ActualDistanceSource: string
{
    case Odometer = 'odometer';
    case Manual = 'manual';
}
