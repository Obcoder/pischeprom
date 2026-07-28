<?php

namespace App\Enums\Logistics;

enum RoutingRunType: string
{
    case CityDistance = 'city_distance';
    case DistanceMatrix = 'distance_matrix';
    case TripRoute = 'trip_route';
    case StaleRefresh = 'stale_refresh';
}
