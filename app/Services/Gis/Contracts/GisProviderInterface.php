<?php

namespace App\Services\Gis\Contracts;

use App\Services\Gis\DTO\DistanceMatrixResult;
use App\Services\Gis\DTO\GeocodeResult;
use App\Services\Gis\DTO\ReverseGeocodeResult;
use App\Services\Gis\DTO\RouteResult;

interface GisProviderInterface
{
    public function code(): string;

    public function geocode(string $address, array $options = []): GeocodeResult;

    public function reverseGeocode(float $lat, float $lon, array $options = []): ReverseGeocodeResult;

    public function buildRoute(array $points, array $options = []): RouteResult;

    public function distanceMatrix(array $origins, array $destinations, array $options = []): DistanceMatrixResult;
}
