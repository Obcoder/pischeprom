<?php

namespace App\Http\Controllers\API\Gis;

use App\Http\Controllers\Controller;
use App\Models\GisRouteDraft;
use App\Services\Gis\Exceptions\GisException;
use App\Services\Gis\RouteDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GisRouteController extends Controller
{
    public function __construct(private readonly RouteDraftService $routes) {}

    public function preview(Request $request): JsonResponse
    {
        $payload = $this->validatedRoutePayload($request);

        try {
            return response()->json($this->routes->preview($payload)->toArray());
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function distanceMatrix(Request $request): JsonResponse
    {
        $payload = $this->validatedMatrixPayload($request);

        try {
            return response()->json($this->routes->distanceMatrix($payload)->toArray());
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedRoutePayload($request, includeName: true);

        try {
            $draft = $this->routes->storeDraft($payload, $request->user()?->id);

            return response()->json([
                'message' => 'Черновик маршрута сохранён.',
                'draft' => $this->routes->payload($draft),
            ], 201);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function show(GisRouteDraft $draft): JsonResponse
    {
        return response()->json([
            'draft' => $this->routes->payload($draft),
        ]);
    }

    public function destroy(GisRouteDraft $draft): JsonResponse
    {
        $draft->delete();

        return response()->json([
            'message' => 'Черновик маршрута удалён.',
        ]);
    }

    private function validatedRoutePayload(Request $request, bool $includeName = false): array
    {
        $rules = [
            'provider' => ['required', 'string', 'in:2gis,yandex'],
            'transport_mode' => ['nullable', 'string', 'in:car,truck,pedestrian'],
            'points' => ['required', 'array', 'min:2'],
            'points.*.entity_id' => ['nullable', 'integer'],
            'points.*.title' => ['nullable', 'string', 'max:255'],
            'points.*.address_text' => ['nullable', 'string', 'max:1024'],
            'points.*.lat' => ['nullable', 'numeric'],
            'points.*.lon' => ['nullable', 'numeric'],
            'points.*.point_type' => ['nullable', 'string', 'in:entity,manual,city,warehouse'],
        ];

        if ($includeName) {
            $rules['name'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules);
    }

    private function validatedMatrixPayload(Request $request): array
    {
        return $request->validate([
            'provider' => ['required', 'string', 'in:2gis,yandex'],
            'transport_mode' => ['nullable', 'string', 'in:car,truck,pedestrian'],
            'origins' => ['required', 'array', 'min:1'],
            'origins.*.entity_id' => ['nullable', 'integer'],
            'origins.*.title' => ['nullable', 'string', 'max:255'],
            'origins.*.address_text' => ['nullable', 'string', 'max:1024'],
            'origins.*.lat' => ['nullable', 'numeric'],
            'origins.*.lon' => ['nullable', 'numeric'],
            'origins.*.point_type' => ['nullable', 'string', 'in:entity,manual,city,warehouse'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.entity_id' => ['nullable', 'integer'],
            'destinations.*.title' => ['nullable', 'string', 'max:255'],
            'destinations.*.address_text' => ['nullable', 'string', 'max:1024'],
            'destinations.*.lat' => ['nullable', 'numeric'],
            'destinations.*.lon' => ['nullable', 'numeric'],
            'destinations.*.point_type' => ['nullable', 'string', 'in:entity,manual,city,warehouse'],
        ]);
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
                'message' => 'Не удалось выполнить GIS-запрос маршрута.',
            ],
        ], 500);
    }
}
