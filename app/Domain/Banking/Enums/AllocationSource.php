<?php

namespace App\Domain\Banking\Enums;

enum AllocationSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
