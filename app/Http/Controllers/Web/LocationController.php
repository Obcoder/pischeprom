<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocationController extends Controller
{
    public function cities(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        return City::query()
            ->with('region:id,name')
            ->where('name', 'like', $query . '%')
            ->orWhere('name', 'like', '%' . $query . '%')
            ->orderByRaw('population IS NULL')
            ->orderByDesc('population')
            ->limit(24)
            ->get()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'region' => $city->region?->name,
                'label' => trim($city->name . ($city->region ? ', ' . $city->region->name : '')),
            ]);
    }

    public function updateCity(Request $request)
    {
        $data = $request->validate([
                                       'city_id' => ['required', 'integer', 'exists:cities,id'],
                                   ]);

        $city = City::query()->findOrFail($data['city_id']);

        if ($request->user()) {
            $request->user()->forceFill([
                                            'city_id' => $city->id,
                                        ])->save();
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
                          sameSite: 'lax'
                      ));

        return back()->with('success', 'Город доставки обновлён.');
    }
}
