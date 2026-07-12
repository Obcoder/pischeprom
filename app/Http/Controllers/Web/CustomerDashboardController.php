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
                'max_chat_id' => $user->max_chat_id ?? null,
                'delivery_address' => $user->delivery_address ?? null,
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

            'orders' => method_exists($user, 'orders')
                ? $user->orders()
                    ->with('items')
                    ->latest('submitted_at')
                    ->latest('id')
                    ->limit(20)
                    ->get()
                    ->map(fn ($order) => [
                        'id' => $order->id,
                        'number' => $order->number,
                        'status' => $order->status,
                        'status_label' => $this->orderStatusLabel($order->status),
                        'total_amount' => $order->total_amount,
                        'total_weight' => $order->total_weight,
                        'currency_code' => $order->currency_code,
                        'delivery_address' => $order->delivery_address,
                        'preferred_delivery_time' => $order->preferred_delivery_time,
                        'submitted_at' => $order->submitted_at?->toIso8601String(),
                        'items' => $order->items->map(fn ($item) => [
                            'id' => $item->id,
                            'good_id' => $item->good_id,
                            'good_name' => $item->good_name,
                            'image_url' => $item->image_url,
                            'quantity' => $item->quantity,
                            'denominator' => $item->denominator,
                            'line_weight' => $item->line_weight,
                            'price_gross' => $item->price_gross,
                            'currency_code' => $item->currency_code,
                            'line_total' => $item->line_total,
                            'country_name' => $item->country_name,
                        ])->values(),
                    ])->values()
                : [],
        ]);
    }

    private function orderStatusLabel(?string $status): string
    {
        return match ($status) {
            'new' => 'Новый',
            'processing' => 'В работе',
            'completed' => 'Завершён',
            'cancelled' => 'Отменён',
            default => 'Создан',
        };
    }
}
