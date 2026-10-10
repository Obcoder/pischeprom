<?php

namespace App\Http\Middleware;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\City;
use App\Services\Auth\StaffAccess;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Realtime\AvitoRealtimeAccess;
use App\Services\Realtime\CommerceRealtimeAccess;
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
        $isStaff = app(StaffAccess::class)->allows($request->user());

        return [
            ...parent::share($request),

            'canLogin' => LaravelRoute::has('login'),
            'canRegister' => LaravelRoute::has('register'),

            'publicCategoryUrls' => fn () => $request->routeIs(
                'home', 'public.catalog.*', 'category.show', 'public.goods.*', 'shop.products.show',
            ) ? app(PublicCatalogService::class)->categoryUrls() : [],

            'realtime' => fn () => app(CommerceRealtimeAccess::class)->clientConfig($request->user()),
            'avitoRealtime' => fn () => app(AvitoRealtimeAccess::class)->clientConfig($request->user()),

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
                    ]
                    : null,
                'permissions' => fn () => [
                    'sales' => [
                        'manage' => $request->user() !== null
                            && $request->user()->status !== 'blocked'
                            && $request->user()->hasVerifiedEmail()
                            && $request->user()->hasRole('admin', 'crm'),
                    ],
                    'sales_mailings' => [
                        'view' => $request->user() !== null
                            && $request->user()->status !== 'blocked'
                            && $request->user()->hasVerifiedEmail()
                            && $request->user()->can('sales_mailings.view'),
                    ],
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
                        'view' => $isStaff && (bool) $request->user()?->can('ai_price_lists.view'),
                        'process' => $isStaff && (bool) $request->user()?->can('ai_price_lists.process'),
                        'review' => $isStaff && (bool) $request->user()?->can('ai_price_lists.review'),
                        'assign_supplier' => $isStaff && (bool) $request->user()?->can('ai_price_lists.assign_supplier'),
                        'apply' => $isStaff && (bool) $request->user()?->can('ai_price_lists.apply'),
                        'view_technical' => $isStaff && (bool) $request->user()?->can('ai_price_lists.view_technical'),
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
