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
        $state = Str::random(40);

        $request->session()->put('yandex_oauth_state', $state);

        return redirect()->away($service->authorizationUrl($state));
    }

    public function callback(Request $request, YandexOAuthService $service): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $expectedState = (string) $request->session()->pull('yandex_oauth_state');
        $actualState = $request->string('state')->toString();

        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            abort(419, 'Сессия подключения Яндекса истекла. Повторите подключение.');
        }

        $service->exchangeCode($request->string('code')->toString(), $request->user()?->id);

        return redirect()->route('Ameise.marketing.yandex-direct');
    }
}
