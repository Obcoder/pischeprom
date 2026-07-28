<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\CalculateMatrixRequest;
use App\Http\Requests\Logistics\ManualCityDistanceRequest;
use App\Http\Resources\Logistics\CityDistanceResource;
use App\Http\Resources\Logistics\RoutingRunResource;
use App\Models\City;
use App\Models\LogisticsCityDistance;
use App\Services\Logistics\CityDistanceMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatrixController extends Controller
{
    public function __construct(private readonly CityDistanceMatrixService $matrix) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LogisticsCityDistance::class);
        [$cityIds, $profile] = $this->validatedSelection($request);

        $cities = City::query()
            ->select(['id', 'name'])
            ->whereIn('id', $cityIds)
            ->with('logisticsSetting:city_id,routing_latitude,routing_longitude,is_matrix_enabled,coordinates_verified_at')
            ->get()
            ->sortBy(fn (City $city) => array_search($city->id, $cityIds, true))
            ->values();
        $distances = LogisticsCityDistance::query()
            ->whereIn('from_city_id', $cityIds)
            ->whereIn('to_city_id', $cityIds)
            ->where('routing_profile', $profile)
            ->where('vehicle_profile_hash', 'default')
            ->get()
            ->keyBy(fn (LogisticsCityDistance $distance) => $distance->from_city_id.':'.$distance->to_city_id);
        $cells = [];

        foreach ($cityIds as $fromId) {
            foreach ($cityIds as $toId) {
                $key = $fromId.':'.$toId;
                $cells[$key] = $fromId === $toId
                    ? [
                        'from_city_id' => $fromId,
                        'to_city_id' => $toId,
                        'status' => 'diagonal',
                        'distance_m' => 0,
                        'duration_s' => 0,
                    ]
                    : ($distances->has($key)
                        ? (new CityDistanceResource($distances[$key]))->resolve($request)
                        : null);
            }
        }

        return response()->json([
            'data' => [
                'routing_profile' => $profile,
                'cities' => $cities->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'is_matrix_enabled' => (bool) $city->logisticsSetting?->is_matrix_enabled,
                    'is_verified' => $city->logisticsSetting?->coordinates_verified_at !== null,
                ])->all(),
                'cells' => $cells,
            ],
        ]);
    }

    public function calculate(CalculateMatrixRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $run = $validated['full_matrix']
            ? $this->matrix->enqueueFullMatrix(
                $validated['routing_profile'],
                (bool) $validated['refresh'],
                (bool) $validated['missing_only'],
                $request->user()?->id,
            )
            : $this->matrix->enqueue(
                $validated['city_ids'],
                $validated['routing_profile'],
                (bool) $validated['refresh'],
                (bool) $validated['missing_only'],
                $request->user()?->id,
            );

        return (new RoutingRunResource($run))
            ->response()
            ->setStatusCode(202);
    }

    public function manual(ManualCityDistanceRequest $request): CityDistanceResource
    {
        return new CityDistanceResource($this->matrix->setManual($request->validated()));
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', LogisticsCityDistance::class);
        [$cityIds, $profile] = $this->validatedSelection($request);
        $cities = City::query()->whereIn('id', $cityIds)->pluck('name', 'id');
        $rows = LogisticsCityDistance::query()
            ->whereIn('from_city_id', $cityIds)
            ->whereIn('to_city_id', $cityIds)
            ->where('routing_profile', $profile)
            ->where('vehicle_profile_hash', 'default')
            ->orderBy('from_city_id')
            ->orderBy('to_city_id')
            ->get();

        return response()->streamDownload(function () use ($rows, $cities): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Город отправления', 'Город назначения', 'Расстояние, км',
                'Время, мин', 'Статус', 'Источник', 'Версия OSM', 'Рассчитано',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $cities[$row->from_city_id] ?? $row->from_city_id,
                    $cities[$row->to_city_id] ?? $row->to_city_id,
                    $row->distance_m === null ? null : number_format($row->distance_m / 1000, 1, ',', ''),
                    $row->duration_s === null ? null : (int) round($row->duration_s / 60),
                    $row->status->value,
                    $row->provider,
                    $row->osm_data_version,
                    $row->calculated_at?->format('Y-m-d H:i:s'),
                ], ';');
            }

            fclose($stream);
        }, 'logistics-distance-matrix-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{0:list<int>,1:string} */
    private function validatedSelection(Request $request): array
    {
        $max = max(2, (int) config('logistics.matrix_max_cities_per_request', 50));
        $validated = Validator::make($request->all(), [
            'city_ids' => ['required', 'array', 'min:2', 'max:'.$max],
            'city_ids.*' => ['required', 'integer', 'distinct', 'exists:logistics_cities,city_id'],
            'routing_profile' => ['nullable', 'string', 'in:truck,auto'],
        ])->validate();

        return [
            array_values(array_map('intval', $validated['city_ids'])),
            $validated['routing_profile'] ?? (string) config('logistics.default_routing_profile', 'truck'),
        ];
    }
}
