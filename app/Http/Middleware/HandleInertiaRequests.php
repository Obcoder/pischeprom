<?php

namespace App\Http\Middleware;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as LaravelRoute;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

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
                        'max_chat_id' => $request->user()->max_chat_id,
                        'delivery_address' => $request->user()->delivery_address,
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
                'permissions' => fn () => [
                    'orders' => [
                        'view' => true,
                        'create' => true,
                        'edit' => true,
                        'delete' => true,
                    ],
                    'ai_sales' => [
                        'view' => (bool) config('ai-sales.enabled')
                            && (bool) config('ai-sales.find_buyers.ui_enabled')
                            && (bool) config('ai-sales.campaigns.enabled')
                            && $request->user() !== null
                            && $request->user()->can('ai_sales.campaigns.view')
                            && app(ProspectingAuthorizationService::class)->can(
                                $request->user(),
                                ProspectingAuthorizationService::VIEW,
                                BusinessLane::Sales,
                            ),
                        'review' => $request->user() !== null
                            && app(ProspectingAuthorizationService::class)->can(
                                $request->user(),
                                ProspectingAuthorizationService::REVIEW,
                                BusinessLane::Sales,
                            ),
                        'resolve' => $request->user() !== null
                            && app(ProspectingAuthorizationService::class)->can(
                                $request->user(),
                                ProspectingAuthorizationService::RESOLVE,
                                BusinessLane::Sales,
                            ),
                    ],
                    'ai_price_lists' => [
                            'view' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.view'),
                            'process' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.process'),
                            'review' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.review'),
                            'assign_supplier' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.assign_supplier'),
                            'apply' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.apply'),
                            'view_technical' => ! config('ai-price-lists.authorization_enabled')
                                || (bool) $request->user()?->can('ai_price_lists.view_technical'),
                    ],
                    'logistics' => [
                            'view' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.view'),
                            'trips_manage' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.trips.manage'),
                            'vehicles_manage' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.vehicles.manage'),
                            'expenses_manage' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.expenses.manage'),
                            'matrix_manage' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.matrix.manage'),
                            'technical_view' => ! config('logistics.authorization_enabled')
                                || (bool) $request->user()?->can('logistics.technical.view'),
                    ],
                ],
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
                'label' => trim($city->name.($city->region ? ', '.$city->region->name : '')),
            ]
            : null;
    }
}
