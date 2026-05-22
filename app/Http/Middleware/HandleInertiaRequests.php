<?php

namespace App\Http\Middleware;

use App\Models\City;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use Illuminate\Support\Facades\Route as LaravelRoute;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'canLogin' => LaravelRoute::has('login'),
            'canRegister' => LaravelRoute::has('register'),

            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'phone' => $request->user()->phone,
                        'type' => $request->user()->type,
                        'status' => $request->user()->status,
                        'account_type' => $request->user()->account_type,
                        'profile_photo_url' => $request->user()->profile_photo_url,
                        'email_verified_at' => $request->user()->email_verified_at,
                        'phone_verified_at' => $request->user()->phone_verified_at,
                        'city_id' => $request->user()->city_id,
                        'user' => fn () => $request->user(),
                    ]
                    : null,
            ],

            'location' => fn () => [
                'city' => $this->resolveCurrentCity($request),
            ],

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    protected function resolveCurrentCity(Request $request): ?array
    {
        $cityId = $request->user()?->city_id ?: $request->cookie('pps_city_id');

        $city = $cityId
            ? City::query()->with('region:id,name')->find($cityId)
            : null;

        if (! $city) {
            $city = City::query()
                ->with('region:id,name')
                ->where('name', 'Санкт-Петербург')
                ->first();
        }

        return $city
            ? [
                'id' => $city->id,
                'name' => $city->name,
                'region' => $city->region?->name,
                'label' => trim($city->name . ($city->region ? ', ' . $city->region->name : '')),
            ]
            : null;
    }
}
