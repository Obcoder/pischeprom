<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\YandexAccount;
use App\Services\Yandex\YandexOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class YandexOAuthController extends Controller
{
    public function redirect(Request $request, YandexOAuthService $service): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put('yandex_oauth_state', $state);

        return redirect()->away($service->authorizationUrl($state));
    }

    public function verificationCode(YandexOAuthService $service): RedirectResponse
    {
        return redirect()->away($service->verificationCodeAuthorizationUrl());
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

        $service->exchangeCode(
            $request->string('code')->toString(),
            $request->user()?->id,
            config('yandex.oauth.redirect_uri')
        );

        return redirect()->route('Ameise.marketing.yandex-direct');
    }

    public function exchangeVerificationCode(Request $request, YandexOAuthService $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:1024'],
        ]);

        try {
            $account = $service->exchangeCode($validated['code'], $request->user()?->id);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'account' => $this->serializeAccount($account),
            'message' => 'OAuth-токен Яндекса сохранён.',
        ]);
    }

    private function serializeAccount(YandexAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'yandex_login' => $account->yandex_login,
            'direct_client_login' => $account->direct_client_login,
            'metrica_counter_id' => $account->metrica_counter_id,
            'token_expires_at' => $account->token_expires_at,
            'scopes' => $account->scopes,
            'is_active' => $account->is_active,
            'last_checked_at' => $account->last_checked_at,
            'has_access_token' => filled($account->access_token),
            'has_refresh_token' => filled($account->refresh_token),
        ];
    }
}
