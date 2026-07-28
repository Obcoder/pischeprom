<?php

namespace App\Enums\Logistics;

enum TemperatureMode: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';
    case Custom = 'custom';
}
