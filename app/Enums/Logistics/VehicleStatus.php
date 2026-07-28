<?php

namespace App\Enums\Logistics;

enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
}
