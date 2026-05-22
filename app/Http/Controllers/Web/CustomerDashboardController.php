<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class CustomerDashboardController extends Controller
{
    public function index()
    {
        $user = request()->user()->load([
                                            'city.region',
                                            'organizations.city',
                                        ]);

        return Inertia::render('Dashboard', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'account_type' => $user->account_type,
                'profile_photo_url' => $user->profile_photo_url,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
                'city' => $user->city
                    ? [
                        'id' => $user->city->id,
                        'name' => $user->city->name,
                        'region' => $user->city->region?->name,
                    ]
                    : null,
            ],
            'organizations' => $user->organizations,
        ]);
    }
}
