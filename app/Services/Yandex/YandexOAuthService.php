<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use App\Models\YandexSyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexOAuthService
{
    public function authorizationUrl(?string $state = null, ?string $redirectUri = null): string
    {
        $clientId = config('yandex.oauth.client_id');

        if (blank($clientId)) {
            throw new RuntimeException('YANDEX_OAUTH_CLIENT_ID не задан.');
        }

        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri ?: config('yandex.oauth.redirect_uri'),
            'scope' => implode(' ', config('yandex.oauth.scopes', [])),
            'state' => $state,
            'force_confirm' => 'yes',
        ], fn ($value) => filled($value));

        return rtrim(config('yandex.oauth.authorize_url'), '?') . '?' . http_build_query($query);
    }

    public function verificationCodeAuthorizationUrl(): string
    {
        return $this->authorizationUrl(null, config('yandex.oauth.verification_redirect_uri'));
    }

    /**
     * @throws ConnectionException
     */
    public function exchangeCode(string $code, ?int $userId = null, ?string $redirectUri = null): YandexAccount
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => config('yandex.oauth.client_id'),
            'client_secret' => config('yandex.oauth.client_secret'),
        ];

        if (filled($redirectUri)) {
            $payload['redirect_uri'] = $redirectUri;
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post(config('yandex.oauth.token_url'), $payload);

        $payload = $response->json() ?: [];

        if (!$response->successful() || blank(Arr::get($payload, 'access_token'))) {
            $this->log(null, 'oauth.exchange', 'error', null, $this->sanitizeTokenPayload($payload), Arr::get($payload, 'error_description') ?: 'OAuth token exchange failed.');
            throw new RuntimeException('Не удалось получить OAuth-токен Яндекса.');
        }

        $expiresIn = (int) Arr::get($payload, 'expires_in', 0);

        $account = YandexAccount::query()->create([
            'user_id' => $userId,
            'name' => 'Yandex OAuth ' . now()->format('Y-m-d H:i'),
            'access_token' => Arr::get($payload, 'access_token'),
            'refresh_token' => Arr::get($payload, 'refresh_token'),
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'scopes' => config('yandex.oauth.scopes', []),
            'metrica_counter_id' => config('yandex.metrica.counter_id'),
            'is_active' => true,
        ]);

        $this->log($account, 'oauth.exchange', 'success', null, ['token_type' => Arr::get($payload, 'token_type')]);

        return $account;
    }

    /**
     * @throws ConnectionException
     */
    public function refreshToken(YandexAccount $account): YandexAccount
    {
        if (blank($account->refresh_token)) {
            throw new RuntimeException('Refresh token отсутствует. Подключите Яндекс заново.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post(config('yandex.oauth.token_url'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => config('yandex.oauth.client_id'),
                'client_secret' => config('yandex.oauth.client_secret'),
            ]);

        $payload = $response->json() ?: [];

        if (!$response->successful() || blank(Arr::get($payload, 'access_token'))) {
            $this->log($account, 'oauth.refresh', 'error', null, $this->sanitizeTokenPayload($payload), Arr::get($payload, 'error_description') ?: 'OAuth token refresh failed.');
            throw new RuntimeException('Не удалось обновить OAuth-токен Яндекса.');
        }

        $expiresIn = (int) Arr::get($payload, 'expires_in', 0);

        $account->update([
            'access_token' => Arr::get($payload, 'access_token'),
            'refresh_token' => Arr::get($payload, 'refresh_token', $account->refresh_token),
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'last_checked_at' => now(),
            'is_active' => true,
        ]);

        $this->log($account, 'oauth.refresh', 'success', null, ['token_type' => Arr::get($payload, 'token_type')]);

        return $account->fresh();
    }

    public function ensureFreshToken(YandexAccount $account): YandexAccount
    {
        if (!$account->token_expires_at instanceof Carbon || $account->token_expires_at->isFuture()) {
            return $account;
        }

        return $this->refreshToken($account);
    }

    public function check(YandexAccount $account): array
    {
        $this->ensureFreshToken($account);

        $account->update([
            'last_checked_at' => now(),
            'is_active' => true,
        ]);

        $this->log($account, 'account.check', 'success', null, ['message' => 'Token is present and refreshable.']);

        return [
            'ok' => true,
            'message' => 'OAuth-токен найден. Проверка прав Директа выполняется при первом запросе к Direct API.',
        ];
    }

    private function sanitizeTokenPayload(array $payload): array
    {
        unset($payload['access_token'], $payload['refresh_token']);

        return $payload;
    }

    private function log(?YandexAccount $account, string $action, string $status, ?array $request = null, ?array $response = null, ?string $error = null): void
    {
        YandexSyncLog::query()->create([
            'yandex_account_id' => $account?->id,
            'entity_type' => $account ? YandexAccount::class : null,
            'entity_id' => $account?->id,
            'action' => $action,
            'status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }
}
