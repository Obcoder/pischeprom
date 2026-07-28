<?php

namespace App\Enums\Logistics;

enum CoordinateSource: string
{
    case Existing = 'existing';
    case Manual = 'manual';
    case Import = 'import';
    case Osm = 'osm';
}
