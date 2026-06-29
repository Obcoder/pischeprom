<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Gis\GisService;
use Inertia\Inertia;
use Inertia\Response;

class GisPageController extends Controller
{
    public function twoGis(GisService $gis): Response
    {
        return $this->providerMap(GisService::PROVIDER_2GIS, $gis);
    }

    public function yandex(GisService $gis): Response
    {
        return $this->providerMap(GisService::PROVIDER_YANDEX, $gis);
    }

    public function providerMap(string $provider, GisService $gis): Response
    {
        $provider = $gis->normalizeProvider($provider);

        return Inertia::render('Gis/ProviderMap', [
            'provider' => $provider,
            'providerLabel' => $provider === GisService::PROVIDER_2GIS ? '2ГИС' : 'Яндекс Карты',
            'mapApiKey' => (string) config("gis.providers.{$provider}.api_key", ''),
            'mapScriptUrl' => (string) config("gis.providers.{$provider}.map_script_url", ''),
            'apiKeyConfigured' => filled(config("gis.providers.{$provider}.api_key")),
            'defaultCenter' => config('gis.map.default_center'),
            'defaultZoom' => config('gis.map.default_zoom'),
        ]);
    }

    public function noLocation(): Response
    {
        return Inertia::render('Gis/EntitiesNoLocation', [
            'providers' => [
                ['value' => GisService::PROVIDER_2GIS, 'title' => '2ГИС'],
                ['value' => GisService::PROVIDER_YANDEX, 'title' => 'Яндекс Карты'],
            ],
            'defaultProvider' => (string) config('gis.default_provider', GisService::PROVIDER_2GIS),
        ]);
    }
}
