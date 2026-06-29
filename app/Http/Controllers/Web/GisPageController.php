<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\EntityLocation;
use App\Models\GisRouteDraft;
use App\Services\Gis\GisService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GisPageController extends Controller
{
    public function dashboard(): Response
    {
        $sourceCounts = EntityLocation::query()
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($total) => (int) $total)
            ->all();

        return Inertia::render('Gis/Dashboard', [
            'overview' => [
                'total_entities' => Entity::query()->count(),
                'with_location' => EntityLocation::query()->count(),
                'without_location' => Entity::query()
                    ->whereDoesntHave('location')
                    ->count(),
                'confirmed_locations' => EntityLocation::query()
                    ->where('is_confirmed', true)
                    ->count(),
                'unconfirmed_locations' => EntityLocation::query()
                    ->where('is_confirmed', false)
                    ->count(),
                'route_drafts' => GisRouteDraft::query()->count(),
                'source_counts' => $sourceCounts,
            ],
            'providers' => $this->providerStatus(),
            'settings' => [
                'default_provider' => (string) config('gis.default_provider', GisService::PROVIDER_2GIS),
                'request_timeout' => (int) config('gis.request_timeout', 10),
                'default_center' => config('gis.map.default_center'),
                'default_zoom' => (int) config('gis.map.default_zoom', 5),
            ],
            'links' => [
                'two_gis' => route('Ameise.gis.2gis'),
                'yandex' => route('Ameise.gis.yandex'),
                'no_location' => route('Ameise.gis.entities.no-location'),
            ],
        ]);
    }

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

    private function providerStatus(): array
    {
        return collect([
            GisService::PROVIDER_2GIS => '2ГИС',
            GisService::PROVIDER_YANDEX => 'Яндекс Карты',
        ])->map(fn (string $label, string $provider) => [
            'value' => $provider,
            'label' => $label,
            'api_key_configured' => filled(config("gis.providers.{$provider}.api_key")),
            'geocode_url_configured' => filled(config("gis.providers.{$provider}.geocode_url")),
            'map_script_url_configured' => filled(config("gis.providers.{$provider}.map_script_url")),
        ])->values()->all();
    }
}
