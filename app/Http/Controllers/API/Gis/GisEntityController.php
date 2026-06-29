<?php

namespace App\Http\Controllers\API\Gis;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Services\Gis\EntityLocationService;
use App\Services\Gis\Exceptions\GisException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GisEntityController extends Controller
{
    public function __construct(private readonly EntityLocationService $locations) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'items' => $this->locations->entitiesWithLocations($request->query())->all(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function noLocation(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'items' => $this->locations->entitiesWithoutLocations($request->query())->all(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function showLocation(int $entityId): JsonResponse
    {
        try {
            $entity = $this->findEntity($entityId);

            return response()->json($this->locations->locationPayload($entity));
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function updateLocation(Request $request, int $entityId): JsonResponse
    {
        $data = $request->validate([
            'address_text' => ['nullable', 'string', 'max:1024'],
            'lat' => ['required', 'numeric'],
            'lon' => ['required', 'numeric'],
            'precision_level' => ['nullable', 'string', 'in:exact,building,street,city,unknown'],
        ]);

        try {
            $entity = $this->findEntity($entityId);
            $location = $this->locations->saveManualLocation($entity, $data);

            return response()->json([
                'message' => 'Координаты entity сохранены.',
                'location' => $this->locations->mapLocation($location),
                'item' => $this->locations->mapEntityItem($entity->refresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function geocode(Request $request, int $entityId): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string', 'in:2gis,yandex'],
            'address' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $entity = $this->findEntity($entityId);
            $result = $this->locations->geocode(
                entity: $entity,
                provider: $data['provider'] ?? null,
                address: $data['address'] ?? null,
            );

            return response()->json([
                'entity' => $this->locations->mapEntityItem($entity, includeCoordinates: false),
                ...$result->toArray(),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function findEntity(int $entityId): Entity
    {
        $entity = Entity::query()->find($entityId);

        if (! $entity) {
            throw new GisException('Entity #'.$entityId.' не найдена.', 404);
        }

        return $entity;
    }

    private function errorResponse(Throwable $exception): JsonResponse
    {
        if ($exception instanceof GisException) {
            return response()->json([
                'error' => [
                    'message' => $exception->getMessage(),
                ],
            ], $exception->httpStatus());
        }

        report($exception);

        return response()->json([
            'error' => [
                'message' => 'Не удалось выполнить GIS-запрос.',
            ],
        ], 500);
    }
}
