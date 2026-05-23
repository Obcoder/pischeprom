<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocationController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        $query = $this->searchTerm($request);

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $likePrefix = $this->likeValue($query) . '%';
        $likeContains = '%' . $this->likeValue($query) . '%';

        $cities = City::query()
            ->with([
                       'region:id,name',
                   ])
            ->where(function (Builder $builder) use ($likePrefix, $likeContains) {
                $builder
                    ->where('name', 'like', $likePrefix)
                    ->orWhere('name', 'like', $likeContains)
                    ->orWhere('wiki_title', 'like', $likeContains)
                    ->orWhere('wiki_summary', 'like', $likeContains)
                    ->orWhereHas('region', function (Builder $regionQuery) use ($likeContains) {
                        $regionQuery->where('name', 'like', $likeContains);
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN name LIKE ? THEN 0
                    WHEN name LIKE ? THEN 1
                    ELSE 2
                END',
                [
                    $likePrefix,
                    $likeContains,
                ],
            )
            ->orderByRaw('population IS NULL')
            ->orderByDesc('population')
            ->orderBy('name')
            ->limit(24)
            ->get()
            ->map(fn (City $city) => $this->formatCityForSelector($city))
            ->values();

        return response()->json($cities);
    }

    public function updateCity(Request $request): RedirectResponse
    {
        $data = $request->validate([
                                       'city_id' => [
                                           'required',
                                           'integer',
                                           'exists:cities,id',
                                       ],
                                   ]);

        $city = City::query()->findOrFail($data['city_id']);

        if ($request->user()) {
            $request->user()
                ->forceFill([
                                'city_id' => $city->id,
                            ])
                ->save();
        }

        Cookie::queue(cookie(
                          name: 'pps_city_id',
                          value: (string) $city->id,
                          minutes: 60 * 24 * 180,
                          path: null,
                          domain: null,
                          secure: $request->isSecure(),
                          httpOnly: true,
                          raw: false,
                          sameSite: 'lax',
                      ));

        return back()->with('success', 'Город доставки обновлён.');
    }

    private function searchTerm(Request $request): string
    {
        foreach (['q', 'search', 'term', 'query'] as $key) {
            $value = $request->query($key);

            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function likeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function formatCityForSelector(City $city): array
    {
        $regionName = $city->region?->name ?: null;

        $label = $regionName
            ? "{$city->name}, {$regionName}"
            : $city->name;

        return [
            'id' => $city->id,
            'name' => $city->name,

            'region' => $regionName,
            'region_name' => $regionName,

            'label' => $label,

            'wiki' => $city->wiki,
            'wiki_title' => $city->wiki_title,
            'wiki_thumbnail' => $city->wiki_thumbnail,

            // Алиасы для фронта, чтобы CitySelector мог подхватить картинку
            // даже если где-то используется старое имя поля.
            'thumbnail' => $city->wiki_thumbnail,
            'image' => $city->wiki_thumbnail,

            'population' => $city->population,
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
        ];
    }
}
