<?php

namespace App\Services\Logistics\Routing\Contracts;

use App\Services\Logistics\Routing\DTO\MatrixRequest;
use App\Services\Logistics\Routing\DTO\MatrixResult;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RouteResult;
use App\Services\Logistics\Routing\DTO\RoutingHealth;

interface RoutingProviderInterface
{
    public function code(): string;

    public function health(): RoutingHealth;

    public function route(RouteRequest $request): RouteResult;

    public function matrix(MatrixRequest $request): MatrixResult;
}
