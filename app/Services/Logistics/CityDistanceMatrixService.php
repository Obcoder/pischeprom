<?php

namespace App\Services\Logistics;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RoutingRunType;
use App\Jobs\Logistics\CalculateDistanceMatrixBatchJob;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsRoutingRun;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\MatrixRequest;
use App\Services\Logistics\Routing\DTO\RoutingPoint;
use App\Services\Logistics\Routing\Support\RoutingHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CityDistanceMatrixService
{
    public function __construct(
        private readonly RoutingProviderInterface $provider,
        private readonly VehicleRoutingProfileFactory $profiles,
        private readonly RoutingRunService $runs,
    ) {}

    public function enqueue(
        array $cityIds,
        string $routingProfile,
        bool $refresh,
        bool $missingOnly,
        ?int $initiatedBy,
        bool $fullMatrix = false,
    ): LogisticsRoutingRun {
        $cityIds = array_values(array_unique(array_map('intval', $cityIds)));

        [$run, $pairs] = DB::transaction(function () use (
            $cityIds, $routingProfile, $refresh, $missingOnly, $initiatedBy, $fullMatrix
        ): array {
            $settings = $this->settings($cityIds, true, true);
            $pairs = $this->pairsForQueue($cityIds, $settings, $routingProfile, $refresh, $missingOnly);
            $run = $this->runs->create(
                RoutingRunType::DistanceMatrix,
                $routingProfile,
                count($pairs),
                $initiatedBy,
                [
                    'city_ids' => $cityIds,
                    'full_matrix' => $fullMatrix,
                    'refresh' => $refresh,
                    'missing_only' => $missingOnly,
                ],
            );

            return [$run, $pairs];
        }, 3);

        if ($pairs === []) {
            return $run;
        }

        $this->dispatchPairs($run, $pairs, $routingProfile);

        return $run->refresh();
    }

    public function enqueueFullMatrix(
        string $routingProfile,
        bool $refresh,
        bool $missingOnly,
        ?int $initiatedBy,
    ): LogisticsRoutingRun {
        $cityIds = $this->fullMatrixCityIds();

        if (count($cityIds) < 2) {
            throw ValidationException::withMessages([
                'full_matrix' => 'Для полной матрицы нужны минимум два включённых города с подтверждёнными точками маршрутизации.',
            ]);
        }

        return $this->enqueue(
            $cityIds,
            $routingProfile,
            $refresh,
            $missingOnly,
            $initiatedBy,
            true,
        );
    }

    /** @return list<int> */
    public function fullMatrixCityIds(): array
    {
        return LogisticsCity::query()
            ->where('is_matrix_enabled', true)
            ->whereNotNull('routing_latitude')
            ->whereNotNull('routing_longitude')
            ->whereNotNull('coordinates_verified_at')
            ->orderBy('city_id')
            ->pluck('city_id')
            ->map(fn ($cityId) => (int) $cityId)
            ->all();
    }

    public function countStalePairs(
        string $routingProfile,
        array $cityIds = [],
        bool $includeFailed = false,
    ): int {
        return $this->stalePairsQuery($routingProfile, $cityIds, $includeFailed)->count();
    }

    public function enqueueStalePairs(
        string $routingProfile,
        int $limit,
        array $cityIds = [],
        bool $includeFailed = false,
        ?int $initiatedBy = null,
    ): LogisticsRoutingRun {
        $limit = max(1, $limit);
        $cityIds = array_values(array_unique(array_map('intval', $cityIds)));

        [$run, $pairs] = DB::transaction(function () use (
            $routingProfile, $limit, $cityIds, $includeFailed, $initiatedBy
        ): array {
            $distances = $this->stalePairsQuery($routingProfile, $cityIds, $includeFailed)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();
            $settingIds = $distances
                ->flatMap(fn (LogisticsCityDistance $distance) => [$distance->from_city_id, $distance->to_city_id])
                ->unique()
                ->values()
                ->all();
            $settings = $this->settings($settingIds);
            $pairs = [];

            foreach ($distances as $distance) {
                $from = $settings[$distance->from_city_id];
                $to = $settings[$distance->to_city_id];
                $requestHash = $this->pairHash($from, $to, $routingProfile);

                $distance->update([
                    'status' => DistanceStatus::Pending,
                    'distance_m' => null,
                    'duration_s' => null,
                    'from_latitude_snapshot' => $from->routing_latitude,
                    'from_longitude_snapshot' => $from->routing_longitude,
                    'to_latitude_snapshot' => $to->routing_latitude,
                    'to_longitude_snapshot' => $to->routing_longitude,
                    'provider' => $this->provider->code(),
                    'routing_engine_version' => null,
                    'osm_data_version' => null,
                    'request_hash' => $requestHash,
                    'calculated_at' => null,
                    'expires_at' => null,
                    'manual_note' => null,
                    'error_code' => null,
                    'error_message' => null,
                ]);
                $pairs[] = [
                    'from_city_id' => $distance->from_city_id,
                    'to_city_id' => $distance->to_city_id,
                    'request_hash' => $requestHash,
                ];
            }

            $run = $this->runs->create(
                RoutingRunType::DistanceMatrix,
                $routingProfile,
                count($pairs),
                $initiatedBy,
                [
                    'city_ids' => $cityIds,
                    'refresh_stale' => true,
                    'include_failed' => $includeFailed,
                    'limit' => $limit,
                ],
            );

            return [$run, $pairs];
        }, 3);

        $this->dispatchPairs($run, $pairs, $routingProfile);

        return $run->refresh();
    }

    /**
     * @param  list<array{from_city_id:int,to_city_id:int,request_hash:string}>  $pairs
     * @return array{completed:int,failed:int}
     */
    public function calculateQueuedPairs(array $pairs, string $routingProfile): array
    {
        $ids = collect($pairs)->flatMap(fn (array $pair) => [
            $pair['from_city_id'], $pair['to_city_id'],
        ])->unique()->values()->all();
        $settings = $this->settings($ids);
        $profile = $this->profiles->make(null, $routingProfile);
        $pendingPairs = [];

        foreach ($pairs as $pair) {
            $existing = LogisticsCityDistance::query()
                ->where('from_city_id', $pair['from_city_id'])
                ->where('to_city_id', $pair['to_city_id'])
                ->where('routing_profile', $routingProfile)
                ->where('vehicle_profile_hash', 'default')
                ->first();

            if ($existing?->status === DistanceStatus::Manual) {
                continue;
            }

            if ($existing?->status === DistanceStatus::Pending
                && hash_equals((string) $existing->request_hash, (string) $pair['request_hash'])) {
                $pendingPairs[] = $pair;
            }
        }

        if ($pendingPairs !== []) {
            $sourceIds = collect($pendingPairs)->pluck('from_city_id')->unique()->values();
            $targetIds = collect($pendingPairs)->pluck('to_city_id')->unique()->values();
            $sources = $sourceIds->map(fn (int $id) => $this->point($settings[$id]))->all();
            $targets = $targetIds->map(fn (int $id) => $this->point($settings[$id]))->all();
            $requestHash = RoutingHash::make([
                'sources' => $sourceIds->all(),
                'targets' => $targetIds->all(),
                'profile' => $profile->toArray(),
                'osm_data_version' => config('logistics.osm_data_version'),
            ]);

            $result = $this->provider->matrix(new MatrixRequest($sources, $targets, $profile, $requestHash));
            $sourceIndex = $sourceIds->flip();
            $targetIndex = $targetIds->flip();

            DB::transaction(function () use (
                $pendingPairs, $settings, $routingProfile, $result, $sourceIndex, $targetIndex
            ): void {
                foreach ($pendingPairs as $pair) {
                    $distance = LogisticsCityDistance::query()
                        ->where('from_city_id', $pair['from_city_id'])
                        ->where('to_city_id', $pair['to_city_id'])
                        ->where('routing_profile', $routingProfile)
                        ->where('vehicle_profile_hash', 'default')
                        ->lockForUpdate()
                        ->first();

                    if (! $distance || $distance->status === DistanceStatus::Manual
                        || ! hash_equals((string) $distance->request_hash, (string) $pair['request_hash'])) {
                        continue;
                    }

                    $cell = $result->cell(
                        (int) $sourceIndex[$pair['from_city_id']],
                        (int) $targetIndex[$pair['to_city_id']],
                    );
                    $from = $settings[$pair['from_city_id']];
                    $to = $settings[$pair['to_city_id']];

                    $distance->update([
                        'status' => $cell?->hasRoute() ? DistanceStatus::Calculated : DistanceStatus::NoRoute,
                        'distance_m' => $cell?->distanceM,
                        'duration_s' => $cell?->durationS,
                        'from_latitude_snapshot' => $from->routing_latitude,
                        'from_longitude_snapshot' => $from->routing_longitude,
                        'to_latitude_snapshot' => $to->routing_latitude,
                        'to_longitude_snapshot' => $to->routing_longitude,
                        'provider' => $result->provider,
                        'routing_engine_version' => $result->routingEngineVersion,
                        'osm_data_version' => $result->osmDataVersion,
                        'calculated_at' => now(),
                        'expires_at' => now()->addDays(max(1, (int) config('logistics.matrix_stale_days', 30))),
                        'error_code' => $cell?->hasRoute() ? null : 'no_route',
                        'error_message' => $cell?->hasRoute() ? null : 'Автомобильный маршрут между городами не найден.',
                    ]);
                }
            }, 3);
        }

        return $this->summarizePairs($pairs, $routingProfile);
    }

    /** @param list<array{from_city_id:int,to_city_id:int,request_hash:string}> $pairs */
    public function markPairsFailed(array $pairs, string $routingProfile, string $code, string $safeMessage): void
    {
        foreach ($pairs as $pair) {
            LogisticsCityDistance::query()
                ->where('from_city_id', $pair['from_city_id'])
                ->where('to_city_id', $pair['to_city_id'])
                ->where('routing_profile', $routingProfile)
                ->where('vehicle_profile_hash', 'default')
                ->where('request_hash', $pair['request_hash'])
                ->where('status', DistanceStatus::Pending->value)
                ->update([
                    'status' => DistanceStatus::Failed->value,
                    'provider' => $this->provider->code(),
                    'error_code' => $code,
                    'error_message' => $safeMessage,
                    'calculated_at' => now(),
                ]);
        }
    }

    public function setManual(array $payload): LogisticsCityDistance
    {
        return DB::transaction(function () use ($payload): LogisticsCityDistance {
            $fromId = (int) $payload['from_city_id'];
            $toId = (int) $payload['to_city_id'];
            $settings = $this->settings([$fromId, $toId], false, true);
            $from = $settings[$fromId];
            $to = $settings[$toId];
            $requestHash = RoutingHash::make([
                'manual' => true,
                'from' => $this->point($from)->toArray(),
                'to' => $this->point($to)->toArray(),
                'profile' => $payload['routing_profile'],
                'distance_m' => (int) $payload['distance_m'],
                'duration_s' => $payload['duration_s'] ?? null,
            ]);
            $distance = LogisticsCityDistance::query()
                ->where('from_city_id', $fromId)
                ->where('to_city_id', $toId)
                ->where('routing_profile', $payload['routing_profile'])
                ->where('vehicle_profile_hash', 'default')
                ->lockForUpdate()
                ->first() ?? new LogisticsCityDistance([
                    'from_city_id' => $fromId,
                    'to_city_id' => $toId,
                    'routing_profile' => $payload['routing_profile'],
                    'vehicle_profile_hash' => 'default',
                ]);

            $distance->fill([
                'status' => DistanceStatus::Manual,
                'distance_m' => $payload['distance_m'],
                'duration_s' => $payload['duration_s'] ?? null,
                'from_latitude_snapshot' => $from->routing_latitude,
                'from_longitude_snapshot' => $from->routing_longitude,
                'to_latitude_snapshot' => $to->routing_latitude,
                'to_longitude_snapshot' => $to->routing_longitude,
                'provider' => 'manual',
                'routing_engine_version' => null,
                'osm_data_version' => config('logistics.osm_data_version'),
                'request_hash' => $requestHash,
                'calculated_at' => now(),
                'expires_at' => null,
                'manual_note' => $payload['manual_note'],
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return $distance;
        }, 3);
    }

    public function markStale(?string $previousOsmVersion = null, bool $dryRun = false): int
    {
        $query = LogisticsCityDistance::query()
            ->where('status', DistanceStatus::Calculated->value)
            ->when($previousOsmVersion, fn ($query) => $query->where('osm_data_version', $previousOsmVersion));
        $count = (clone $query)->count();

        if (! $dryRun) {
            $query->update(['status' => DistanceStatus::Stale->value]);
        }

        return $count;
    }

    private function pairsForQueue(
        array $cityIds,
        Collection $settings,
        string $routingProfile,
        bool $refresh,
        bool $missingOnly,
    ): array {
        $existing = LogisticsCityDistance::query()
            ->whereIn('from_city_id', $cityIds)
            ->whereIn('to_city_id', $cityIds)
            ->where('routing_profile', $routingProfile)
            ->where('vehicle_profile_hash', 'default')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (LogisticsCityDistance $distance) => $distance->from_city_id.':'.$distance->to_city_id);
        $pairs = [];

        DB::transaction(function () use (
            $cityIds, $settings, $routingProfile, $refresh, $missingOnly, $existing, &$pairs
        ): void {
            foreach ($cityIds as $fromId) {
                foreach ($cityIds as $toId) {
                    if ($fromId === $toId) {
                        continue;
                    }

                    $distance = $existing->get($fromId.':'.$toId);
                    if ($distance?->status === DistanceStatus::Manual) {
                        continue;
                    }

                    $expired = $distance?->expires_at?->isPast() ?? false;
                    $needsCalculation = $refresh
                        || ! $distance
                        || $expired
                        || in_array($distance->status, [DistanceStatus::Stale, DistanceStatus::Failed], true)
                        || (! $missingOnly && $distance->status === DistanceStatus::NoRoute);

                    if (! $needsCalculation || $distance?->status === DistanceStatus::Pending) {
                        continue;
                    }

                    $from = $settings[$fromId];
                    $to = $settings[$toId];
                    $requestHash = $this->pairHash($from, $to, $routingProfile);
                    LogisticsCityDistance::query()->updateOrCreate([
                        'from_city_id' => $fromId,
                        'to_city_id' => $toId,
                        'routing_profile' => $routingProfile,
                        'vehicle_profile_hash' => 'default',
                    ], [
                        'status' => DistanceStatus::Pending,
                        'distance_m' => null,
                        'duration_s' => null,
                        'from_latitude_snapshot' => $from->routing_latitude,
                        'from_longitude_snapshot' => $from->routing_longitude,
                        'to_latitude_snapshot' => $to->routing_latitude,
                        'to_longitude_snapshot' => $to->routing_longitude,
                        'provider' => $this->provider->code(),
                        'routing_engine_version' => null,
                        'osm_data_version' => null,
                        'request_hash' => $requestHash,
                        'calculated_at' => null,
                        'expires_at' => null,
                        'manual_note' => null,
                        'error_code' => null,
                        'error_message' => null,
                    ]);
                    $pairs[] = [
                        'from_city_id' => $fromId,
                        'to_city_id' => $toId,
                        'request_hash' => $requestHash,
                    ];
                }
            }
        }, 3);

        return $pairs;
    }

    private function stalePairsQuery(
        string $routingProfile,
        array $cityIds,
        bool $includeFailed,
    ): Builder {
        $cityIds = array_values(array_unique(array_map('intval', $cityIds)));

        return LogisticsCityDistance::query()
            ->where('routing_profile', $routingProfile)
            ->where('vehicle_profile_hash', 'default')
            ->where(function (Builder $query) use ($includeFailed): void {
                $query
                    ->where('status', DistanceStatus::Stale->value)
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('status', DistanceStatus::Calculated->value)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });

                if ($includeFailed) {
                    $query->orWhere('status', DistanceStatus::Failed->value);
                }
            })
            ->when($cityIds !== [], function (Builder $query) use ($cityIds): void {
                $query->whereIn('from_city_id', $cityIds)->whereIn('to_city_id', $cityIds);
            })
            ->whereHas('fromCity.logisticsSetting', function (Builder $query): void {
                $query
                    ->where('is_matrix_enabled', true)
                    ->whereNotNull('routing_latitude')
                    ->whereNotNull('routing_longitude')
                    ->whereNotNull('coordinates_verified_at');
            })
            ->whereHas('toCity.logisticsSetting', function (Builder $query): void {
                $query
                    ->where('is_matrix_enabled', true)
                    ->whereNotNull('routing_latitude')
                    ->whereNotNull('routing_longitude')
                    ->whereNotNull('coordinates_verified_at');
            });
    }

    /** @param list<array{from_city_id:int,to_city_id:int,request_hash:string}> $pairs */
    private function dispatchPairs(
        LogisticsRoutingRun $run,
        array $pairs,
        string $routingProfile,
    ): void {
        if ($pairs === []) {
            return;
        }

        $batchSize = max(2, (int) config('logistics.matrix_batch_cities', 10));
        $sourceChunks = collect($pairs)->pluck('from_city_id')->unique()->values()->chunk($batchSize);
        $targetChunks = collect($pairs)->pluck('to_city_id')->unique()->values()->chunk($batchSize);

        foreach ($sourceChunks as $sourceChunk) {
            foreach ($targetChunks as $targetChunk) {
                $sourceIds = $sourceChunk->all();
                $targetIds = $targetChunk->all();
                $batchPairs = array_values(array_filter($pairs, fn (array $pair) => in_array($pair['from_city_id'], $sourceIds, true)
                    && in_array($pair['to_city_id'], $targetIds, true)
                ));

                if ($batchPairs !== []) {
                    CalculateDistanceMatrixBatchJob::dispatch($run->id, $batchPairs, $routingProfile);
                }
            }
        }
    }

    private function settings(
        array $cityIds,
        bool $requireMatrixEnabled = true,
        bool $lockForUpdate = false,
    ): Collection {
        $settings = LogisticsCity::query()
            ->with('city:id,name')
            ->whereIn('city_id', $cityIds)
            ->orderBy('city_id')
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->get()
            ->keyBy('city_id');

        foreach ($cityIds as $cityId) {
            $setting = $settings->get($cityId);
            if (! $setting) {
                throw ValidationException::withMessages(['city_ids' => "Город #{$cityId} не включён в логистику."]);
            }
            if ($requireMatrixEnabled && ! $setting->is_matrix_enabled) {
                throw ValidationException::withMessages(['city_ids' => "Город «{$setting->city?->name}» отключён от матрицы."]);
            }
            if (! $setting->hasRoutingPoint() || ! $setting->coordinates_verified_at) {
                throw ValidationException::withMessages([
                    'city_ids' => "У города «{$setting->city?->name}» не подтверждена точка маршрутизации. Укажите координаты на доступной автомобильной дороге и подтвердите их в настройках города.",
                ]);
            }
        }

        return $settings;
    }

    private function point(LogisticsCity $city): RoutingPoint
    {
        return new RoutingPoint(
            (float) $city->routing_latitude,
            (float) $city->routing_longitude,
            $city->city?->name,
        );
    }

    private function pairHash(LogisticsCity $from, LogisticsCity $to, string $profile): string
    {
        return RoutingHash::make([
            'from' => $this->point($from)->toArray(),
            'to' => $this->point($to)->toArray(),
            'profile' => $profile,
            'vehicle_profile_hash' => 'default',
            'provider' => $this->provider->code(),
            'osm_data_version' => config('logistics.osm_data_version'),
        ]);
    }

    private function summarizePairs(array $pairs, string $routingProfile): array
    {
        $completed = 0;
        $failed = 0;

        foreach ($pairs as $pair) {
            $distance = LogisticsCityDistance::query()
                ->where('from_city_id', $pair['from_city_id'])
                ->where('to_city_id', $pair['to_city_id'])
                ->where('routing_profile', $routingProfile)
                ->where('vehicle_profile_hash', 'default')
                ->first(['status', 'request_hash']);

            if ($distance?->status === DistanceStatus::Manual) {
                $completed++;

                continue;
            }

            $sameRequest = $distance
                && hash_equals((string) $distance->request_hash, (string) $pair['request_hash']);

            if ($sameRequest && $distance->status === DistanceStatus::Calculated) {
                $completed++;
            } else {
                $failed++;
            }
        }

        return ['completed' => $completed, 'failed' => $failed];
    }
}
