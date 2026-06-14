<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Yandex\YandexOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class YandexOAuthController extends Controller
{
    public function redirect(Request $request, YandexOAuthService $service): RedirectResponse
    {
        return redirect()->away($service->authorizationUrl(Str::random(40)));
    }

    public function callback(Request $request, YandexOAuthService $service): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $service->exchangeCode($request->string('code')->toString(), $request->user()?->id);

        return redirect()->route('Ameise.marketing.yandex-direct');
    }
}
