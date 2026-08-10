<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\AvitoConnection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AvitoTokenManager
{
    public function clientCredentialsToken(): string
    {
        $clientId = trim((string) config('avito.client_id'));
        $clientSecret = trim((string) config('avito.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            throw new AvitoException(
                'Не заданы AVITO_CLIENT_ID и AVITO_CLIENT_SECRET.',
                'configuration',
                422
            );
        }

        $cacheKey = 'avito:client-credentials:'.hash('sha256', $clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            return Cache::lock($cacheKey.':lock', 20)->block(5, function () use ($cacheKey, $clientId, $clientSecret): string {
                $cached = Cache::get($cacheKey);

                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }

                $response = $this->tokenRequest([
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
                $payload = $response->json() ?: [];
                $token = (string) Arr::get($payload, 'access_token', '');

                if (! $response->successful() || $token === '') {
                    throw $this->tokenException($response, 'Avito отклонил персональную авторизацию.');
                }

                $ttl = max(60, ((int) Arr::get($payload, 'expires_in', 86400)) - 120);
                Cache::put($cacheKey, $token, now()->addSeconds($ttl));

                return $token;
            });
        } catch (LockTimeoutException) {
            throw new AvitoException('Получение токена Avito уже выполняется. Повторите запрос.', 'token_lock', 503, true);
        }
    }

    public function authorizationUrl(string $state): string
    {
        $clientId = trim((string) config('avito.client_id'));

        if ($clientId === '') {
            throw new AvitoException('AVITO_CLIENT_ID не задан.', 'configuration', 422);
        }

        return $this->validatedAuthorizeUrl().'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', (array) config('avito.oauth_scopes', [])),
            'state' => $state,
            'pro_users_flow' => 'true',
        ]);
    }

    public function exchangeAuthorizationCode(string $code): AvitoConnection
    {
        $this->assertClientConfiguration();
        $this->validatedApiBaseUrl();

        $response = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => config('avito.client_id'),
            'client_secret' => config('avito.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
        $payload = $response->json() ?: [];

        if (! $response->successful() || blank(Arr::get($payload, 'access_token'))) {
            throw $this->tokenException($response, 'Avito отклонил код авторизации.');
        }

        $connection = AvitoConnection::query()->create([
            'name' => 'Avito OAuth '.now()->format('d.m.Y H:i'),
            'auth_mode' => 'authorization_code',
            'access_token' => Arr::get($payload, 'access_token'),
            'refresh_token' => Arr::get($payload, 'refresh_token'),
            'token_expires_at' => now()->addSeconds(max(60, (int) Arr::get($payload, 'expires_in', 86400))),
            'scopes' => $this->payloadScopes($payload),
            'status' => 'active',
            'is_active' => true,
            'last_checked_at' => now(),
        ]);

        $this->hydrateAccountIdentity($connection);

        return $connection->fresh();
    }

    public function refresh(AvitoConnection $connection): AvitoConnection
    {
        $observedRefreshToken = (string) $connection->refresh_token;

        try {
            return Cache::lock("avito:oauth-refresh:{$connection->id}", 30)
                ->block(8, fn () => $this->refreshLocked($connection, $observedRefreshToken));
        } catch (LockTimeoutException) {
            throw new AvitoException('OAuth-токен Avito уже обновляется. Повторите запрос.', 'token_lock', 503, true);
        }
    }

    public function oauthToken(AvitoConnection $connection): string
    {
        if (! $connection->is_active) {
            throw new AvitoException('Подключение Avito отключено.', 'authorization', 401);
        }

        if ($connection->token_expires_at?->lte(now()->addMinute())) {
            $connection = $this->refresh($connection);
        }

        if (blank($connection->access_token)) {
            throw new AvitoException('OAuth-токен Avito отсутствует.', 'authorization', 401);
        }

        return (string) $connection->access_token;
    }

    public function tokenFor(array $capability, ?AvitoConnection $connection = null): ?string
    {
        $schemes = collect($capability['security'] ?? [])->pluck('scheme')->all();
        $documentsBearerHeader = collect($capability['parameters'] ?? [])->contains(
            fn (array $parameter): bool => ($parameter['in'] ?? null) === 'header'
                && strcasecmp((string) ($parameter['name'] ?? ''), 'Authorization') === 0
                && (bool) ($parameter['required'] ?? false)
        );
        $autoloadBearerOperation = ($capability['section'] ?? null) === 'autoload';

        if ($schemes === [] && ! $documentsBearerHeader && ! $autoloadBearerOperation) {
            return null;
        }

        // The official Autoload documents omit OpenAPI `security` on the whole
        // section and sometimes omit the Authorization parameter as well, even
        // though those endpoints return 403 without a Bearer token.
        if ($schemes === [] && ($documentsBearerHeader || $autoloadBearerOperation)) {
            if ($connection) {
                return $this->oauthToken($connection);
            }

            return $this->clientCredentialsToken();
        }

        if ($connection) {
            if (! in_array('authorization_code', $schemes, true)) {
                throw new AvitoException('Эта функция не поддерживает выбранное OAuth-подключение.', 'authorization_scheme', 422);
            }

            $this->assertScopes($capability, $connection);

            return $this->oauthToken($connection);
        }

        if (in_array('client_credentials', $schemes, true) && $this->clientCredentialsConfigured()) {
            return $this->clientCredentialsToken();
        }

        if (in_array('authorization_code', $schemes, true)) {
            $connection = AvitoConnection::query()->where('is_active', true)->latest('id')->first();

            if ($connection) {
                $this->assertScopes($capability, $connection);

                return $this->oauthToken($connection);
            }
        }

        if (in_array('client_credentials', $schemes, true)) {
            return $this->clientCredentialsToken();
        }

        throw new AvitoException('Для функции требуется OAuth-подключение аккаунта Avito.', 'authorization', 401);
    }

    public function clientCredentialsConfigured(): bool
    {
        return filled(config('avito.client_id')) && filled(config('avito.client_secret'));
    }

    public function redirectUri(): string
    {
        return (string) (config('avito.redirect_uri') ?: route('api.avito.oauth.callback'));
    }

    private function tokenRequest(array $payload): Response
    {
        try {
            return Http::asForm()
                ->acceptJson()
                ->withUserAgent('Pischeprom-Ameise-Avito/1.0')
                ->connectTimeout((int) config('avito.connect_timeout_seconds'))
                ->timeout((int) config('avito.timeout_seconds'))
                ->post($this->validatedTokenUrl(), $payload);
        } catch (ConnectionException) {
            throw new AvitoException('Сервис авторизации Avito недоступен.', 'network', 502, true);
        }
    }

    private function tokenException(Response $response, string $fallback): AvitoException
    {
        $payload = $response->json() ?: [];
        $message = (string) (Arr::get($payload, 'error_description') ?: Arr::get($payload, 'message') ?: $fallback);

        return new AvitoException(Str::limit(strip_tags($message), 500), 'authorization', 401);
    }

    private function validatedTokenUrl(): string
    {
        $url = rtrim((string) config('avito.token_url'), '/');

        if (! $this->isExactAvitoUrl($url, 'api.avito.ru', '/token')) {
            throw new AvitoException('AVITO_TOKEN_URL не прошёл серверный allowlist.', 'configuration', 503);
        }

        return $url;
    }

    private function validatedAuthorizeUrl(): string
    {
        $url = rtrim((string) config('avito.authorize_url'), '/?');

        if (! $this->isExactAvitoUrl($url, 'avito.ru', '/oauth')) {
            throw new AvitoException('AVITO_AUTHORIZE_URL не прошёл серверный allowlist.', 'configuration', 503);
        }

        return $url;
    }

    private function validatedApiBaseUrl(): string
    {
        $url = rtrim((string) config('avito.api_base_url'), '/');

        if (! $this->isExactAvitoUrl($url, 'api.avito.ru', '')) {
            throw new AvitoException('AVITO_API_URL не прошёл серверный allowlist.', 'configuration', 503);
        }

        return $url;
    }

    private function isExactAvitoUrl(string $url, string $host, string $path): bool
    {
        $port = parse_url($url, PHP_URL_PORT);

        return Str::lower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && Str::lower((string) parse_url($url, PHP_URL_HOST)) === $host
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === rtrim($path, '/')
            && ($port === null || $port === 443)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_QUERY) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null;
    }

    private function payloadScopes(array $payload, array $fallback = []): array
    {
        $scope = Arr::get($payload, 'scope');

        if (is_array($scope)) {
            return array_values($scope);
        }

        if (is_string($scope) && $scope !== '') {
            return preg_split('/[\s,]+/', trim($scope)) ?: $fallback;
        }

        return $fallback ?: (array) config('avito.oauth_scopes', []);
    }

    private function assertClientConfiguration(): void
    {
        if (! $this->clientCredentialsConfigured()) {
            throw new AvitoException('Не заданы AVITO_CLIENT_ID и AVITO_CLIENT_SECRET.', 'configuration', 422);
        }
    }

    private function refreshLocked(AvitoConnection $connection, string $observedRefreshToken): AvitoConnection
    {
        $connection = $connection->fresh();

        if (! $connection) {
            throw new AvitoException('OAuth-подключение Avito удалено.', 'authorization', 404);
        }

        if ($observedRefreshToken !== ''
            && ! hash_equals($observedRefreshToken, (string) $connection->refresh_token)
            && $connection->token_expires_at?->isFuture()) {
            return $connection;
        }

        if (blank($connection->refresh_token)) {
            $connection->update(['status' => 'reauthorization_required']);

            throw new AvitoException('Refresh token Avito отсутствует. Подключите аккаунт повторно.', 'authorization', 401);
        }

        $this->assertClientConfiguration();
        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => config('avito.client_id'),
            'client_secret' => config('avito.client_secret'),
            'refresh_token' => $connection->refresh_token,
        ]);
        $payload = $response->json() ?: [];

        if (! $response->successful() || blank(Arr::get($payload, 'access_token'))) {
            $exception = $this->tokenException($response, 'Не удалось обновить OAuth-токен Avito.');
            $connection->update([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
                'last_checked_at' => now(),
            ]);

            throw $exception;
        }

        $connection->update([
            'access_token' => Arr::get($payload, 'access_token'),
            'refresh_token' => Arr::get($payload, 'refresh_token', $connection->refresh_token),
            'token_expires_at' => now()->addSeconds(max(60, (int) Arr::get($payload, 'expires_in', 86400))),
            'scopes' => $this->payloadScopes($payload, $connection->scopes ?: []),
            'status' => 'active',
            'is_active' => true,
            'last_error' => null,
            'last_checked_at' => now(),
        ]);

        return $connection->fresh();
    }

    private function assertScopes(array $capability, AvitoConnection $connection): void
    {
        $requiredAlternatives = collect($capability['security'] ?? [])
            ->where('scheme', 'authorization_code')
            ->pluck('scopes')
            ->all();

        if ($requiredAlternatives === []) {
            return;
        }

        $granted = $connection->scopes ?: [];
        $satisfied = collect($requiredAlternatives)->contains(
            fn (array $required) => count(array_diff($required, $granted)) === 0
        );

        if (! $satisfied) {
            throw new AvitoException('OAuth-подключению не хватает scope для этой функции.', 'authorization_scope', 403);
        }
    }

    private function hydrateAccountIdentity(AvitoConnection $connection): void
    {
        try {
            $response = Http::withToken((string) $connection->access_token)
                ->acceptJson()
                ->connectTimeout((int) config('avito.connect_timeout_seconds'))
                ->timeout((int) config('avito.timeout_seconds'))
                ->get($this->validatedApiBaseUrl().'/core/v1/accounts/self');

            if (! $response->successful() || ! is_array($response->json())) {
                return;
            }

            $payload = $response->json();
            $connection->update([
                'external_user_id' => Arr::get($payload, 'id'),
                'name' => (string) (Arr::get($payload, 'name') ?: Arr::get($payload, 'email') ?: $connection->name),
                'metadata' => Arr::only($payload, ['name', 'email', 'phone', 'profile_url']),
            ]);
        } catch (\Throwable) {
            // Identity enrichment is optional; the OAuth token is already valid.
        }
    }
}
