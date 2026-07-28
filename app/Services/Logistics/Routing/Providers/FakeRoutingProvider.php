<?php

namespace App\Services\Logistics\Routing\Providers;

use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\MatrixCell;
use App\Services\Logistics\Routing\DTO\MatrixRequest;
use App\Services\Logistics\Routing\DTO\MatrixResult;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RouteResult;
use App\Services\Logistics\Routing\DTO\RoutingHealth;
use Throwable;

class FakeRoutingProvider implements RoutingProviderInterface
{
    public ?RouteResult $routeResult = null;

    public ?MatrixResult $matrixResult = null;

    public ?Throwable $routeException = null;

    public ?Throwable $matrixException = null;

    public bool $healthy = true;

    public int $routeCalls = 0;

    public int $matrixCalls = 0;

    public function code(): string
    {
        return 'fake';
    }

    public function health(): RoutingHealth
    {
        return new RoutingHealth(
            healthy: $this->healthy,
            provider: $this->code(),
            routingEngineVersion: 'fake-1.0',
            osmDataVersion: 'fixture',
            latencyMs: 0,
            message: $this->healthy ? null : 'Fake provider is unhealthy.',
        );
    }

    public function route(RouteRequest $request): RouteResult
    {
        $this->routeCalls++;

        if ($this->routeException) {
            throw $this->routeException;
        }

        return $this->routeResult ?? new RouteResult(
            distanceM: (count($request->points) - 1) * 100_000,
            durationS: (count($request->points) - 1) * 7_200,
            shapePolyline6: null,
            legs: [],
            provider: $this->code(),
            routingEngineVersion: 'fake-1.0',
            osmDataVersion: 'fixture',
        );
    }

    public function matrix(MatrixRequest $request): MatrixResult
    {
        $this->matrixCalls++;

        if ($this->matrixException) {
            throw $this->matrixException;
        }

        if ($this->matrixResult) {
            return $this->matrixResult;
        }

        $cells = [];
        foreach ($request->sources as $sourceIndex => $_source) {
            foreach ($request->targets as $targetIndex => $_target) {
                $samePoint = $_source->latitude === $_target->latitude
                    && $_source->longitude === $_target->longitude;
                $cells[] = new MatrixCell(
                    sourceIndex: $sourceIndex,
                    targetIndex: $targetIndex,
                    distanceM: $samePoint ? 0 : 100_000 + (($sourceIndex + $targetIndex) * 1_000),
                    durationS: $samePoint ? 0 : 7_200 + (($sourceIndex + $targetIndex) * 60),
                );
            }
        }

        return new MatrixResult($cells, $this->code(), 'fake-1.0', 'fixture');
    }
}
