<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $relations = [];

        if (method_exists($user, 'city')) {
            $relations[] = 'city.region';
        }

        if (method_exists($user, 'entities')) {
            $relations[] = 'entities.classification';
            $relations[] = 'entities.cities';
        }

        if (! empty($relations)) {
            $user->loadMissing($relations);
        }

        return Inertia::render('Dashboard', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'type' => $user->type ?? null,
                'status' => $user->status ?? null,
                'account_type' => $user->account_type ?? 'individual',
                'profile_photo_url' => $user->profile_photo_url ?? null,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at ?? null,

                'city' => $user->relationLoaded('city') && $user->city
                    ? [
                        'id' => $user->city->id,
                        'name' => $user->city->name,
                        'region' => $user->city->region?->name,
                    ]
                    : null,
            ],

            'entities' => $user->relationLoaded('entities')
                ? $user->entities->map(fn ($entity) => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'INN' => $entity->INN ?? null,
                    'KPP' => $entity->KPP ?? null,
                    'OGRN' => $entity->OGRN ?? null,
                    'classification' => $entity->classification?->name,
                    'cities' => $entity->relationLoaded('cities')
                        ? $entity->cities->map(fn ($city) => [
                            'id' => $city->id,
                            'name' => $city->name,
                        ])->values()
                        : [],
                ])->values()
                : [],
        ]);
    }
}
