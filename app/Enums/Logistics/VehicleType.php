<?php

namespace App\Enums\Logistics;

enum VehicleType: string
{
    case Truck = 'truck';
    case Van = 'van';
    case Tractor = 'tractor';
    case Refrigerated = 'refrigerated';
    case Other = 'other';
}
