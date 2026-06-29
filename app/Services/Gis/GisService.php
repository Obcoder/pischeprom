<?php

namespace App\Services\Gis;

use App\Services\Gis\Contracts\GisProviderInterface;
use App\Services\Gis\Exceptions\GisValidationException;
use App\Services\Gis\Providers\TwoGisProvider;
use App\Services\Gis\Providers\YandexProvider;

class GisService
{
    public const PROVIDER_2GIS = '2gis';

    public const PROVIDER_YANDEX = 'yandex';

    public const TRANSPORT_MODES = [
        'car',
        'truck',
        'pedestrian',
    ];

    public function provider(?string $provider = null): GisProviderInterface
    {
        $provider = $this->normalizeProvider($provider ?: config('gis.default_provider'));

        return match ($provider) {
            self::PROVIDER_2GIS => app(TwoGisProvider::class),
            self::PROVIDER_YANDEX => app(YandexProvider::class),
            default => throw new GisValidationException('Неизвестный GIS-провайдер.'),
        };
    }

    public function normalizeProvider(?string $provider): string
    {
        $provider = strtolower(trim((string) $provider));

        if ($provider === 'twogis' || $provider === 'dgis') {
            $provider = self::PROVIDER_2GIS;
        }

        if (! in_array($provider, [self::PROVIDER_2GIS, self::PROVIDER_YANDEX], true)) {
            throw new GisValidationException('Неизвестный GIS-провайдер. Доступны: 2gis, yandex.');
        }

        return $provider;
    }

    public function normalizeTransportMode(?string $transportMode): string
    {
        $transportMode = strtolower(trim((string) ($transportMode ?: 'car')));

        if (! in_array($transportMode, self::TRANSPORT_MODES, true)) {
            throw new GisValidationException('Некорректный тип транспорта. Доступны: car, truck, pedestrian.');
        }

        return $transportMode;
    }

    public function assertCoordinates(mixed $lat, mixed $lon): array
    {
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            throw new GisValidationException('Координаты должны быть числовыми.');
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new GisValidationException('Координаты вне допустимого диапазона.');
        }

        return [$lat, $lon];
    }
}
