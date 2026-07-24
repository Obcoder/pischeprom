<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\DTO\BankTokensData;
use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Enums\BankProvider;
use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankConfigurationException;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Domain\Banking\Exceptions\ReadOnlyViolationException;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Models\BankConnection;
use App\Models\BankOAuthAttempt;
use App\Models\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SberOAuthService
{
    private const ALLOWED_SCOPES = [
        'openid',
        'GET_STATEMENT_ACCOUNT',
    ];

    public function __construct(
        private readonly SberReadOnlyApiClient $client,
        private readonly SecretFileReader $secrets,
        private readonly SberIdTokenValidator $idTokens,
        private readonly BankAuditLogger $audit,
    ) {}

    public function authorizationUrl(User $user, ?Entity $ownerEntity = null): string
    {
        $this->assertOAuthConfiguration();
        $environment = BankEnvironment::from((string) config('banking.sber.environment', 'sandbox'));
        $state = $this->randomToken();
        $nonce = $this->randomToken();

        BankOAuthAttempt::query()->create([
            'provider' => BankProvider::Sber->value,
            'environment' => $environment->value,
            'state_hash' => hash('sha256', $state),
            'nonce_hash' => hash('sha256', $nonce),
            'owner_entity_id' => $ownerEntity?->id,
            'initiated_by' => $user->id,
            'expires_at' => now()->addMinutes((int) config('banking.sber.oauth_state_ttl_minutes', 10)),
        ]);

        $base = $this->client->validatedBaseUrl($environment, 'authorization');

        return $base.'/ic/sso/api/v2/oauth/authorize?'.http_build_query([
            'prompt' => 'login',
            'redirect_uri' => (string) config('banking.sber.redirect_uri'),
            'nonce' => $nonce,
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
            'response_type' => 'code',
            'client_id' => (string) config('banking.sber.client_id'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(
        string $code,
        string $state,
        ?string $error = null,
        ?string $errorDescription = null,
    ): BankConnection {
        $stateHash = hash('sha256', $state);
        $attempt = DB::transaction(function () use ($stateHash, $error, $errorDescription): BankOAuthAttempt {
            /** @var BankOAuthAttempt|null $attempt */
            $attempt = BankOAuthAttempt::query()
                ->where('state_hash', $stateHash)
                ->lockForUpdate()
                ->first();

            if (! $attempt || $attempt->consumed_at !== null || $attempt->expires_at->isPast()) {
                throw new BankAuthenticationException('Sber OAuth state is invalid, expired, or already used.');
            }

            $attempt->forceFill([
                'consumed_at' => now(),
                'callback_error' => $this->safeCallbackError($error, $errorDescription),
            ])->save();

            return $attempt;
        }, 3);

        if ($error !== null && $error !== '') {
            $this->audit->record('bank.oauth.callback_failed', $attempt, [
                'error' => mb_substr($error, 0, 128),
            ], $attempt->initiated_by);

            throw new BankAuthenticationException('Sber authorization was denied or failed.');
        }

        if ($code === '') {
            throw new BankAuthenticationException('Sber authorization code is missing.');
        }

        $environment = BankEnvironment::from($attempt->environment);
        $tokens = $this->requestTokens($environment, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => (string) config('banking.sber.client_id'),
            'client_secret' => $this->secrets->read(
                config('banking.sber.client_secret_file'),
                'Sber client secret'
            ),
            'redirect_uri' => (string) config('banking.sber.redirect_uri'),
        ]);
        $claims = [];

        if ($tokens->idToken !== null) {
            $claims = $this->idTokens->validate($tokens->idToken, $attempt->nonce_hash);
        }

        $connection = DB::transaction(function () use ($attempt, $environment, $tokens, $claims): BankConnection {
            $connection = BankConnection::query()
                ->where('provider', BankProvider::Sber->value)
                ->where('environment', $environment->value)
                ->where('owner_entity_id', $attempt->owner_entity_id)
                ->lockForUpdate()
                ->first() ?? new BankConnection;

            $connection->forceFill([
                'provider' => BankProvider::Sber,
                'owner_entity_id' => $attempt->owner_entity_id,
                'environment' => $environment,
                'status' => BankConnectionStatus::Active,
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'access_token_expires_at' => $tokens->accessTokenExpiresAt,
                'refresh_token_expires_at' => $tokens->refreshTokenExpiresAt,
                'scopes' => $tokens->scopes ?: $this->scopes(),
                'connected_by' => $attempt->initiated_by,
                'connected_at' => now(),
                'last_error_at' => null,
                'client_secret_expires_at' => $this->configuredDate('banking.sber.client_secret_expires_at'),
                'mtls_certificate_expires_at' => $this->certificateExpiry(),
                'settings' => [
                    ...($connection->settings ?? []),
                    'sber_subject' => isset($claims['sub']) ? hash('sha256', (string) $claims['sub']) : null,
                    'offer_expiration_at' => $claims['offerExpirationDate'] ?? null,
                ],
            ])->save();

            return $connection->fresh();
        }, 3);

        $this->audit->record('bank.connection.connected', $connection, [
            'provider' => BankProvider::Sber->value,
            'environment' => $environment->value,
            'scopes' => $connection->scopes,
        ], $attempt->initiated_by);

        return $connection;
    }

    public function refresh(BankConnection $connection): BankTokensData
    {
        if (! $connection->refresh_token) {
            throw new BankAuthenticationException('Sber refresh token is unavailable.');
        }

        return $this->requestTokens($connection->environment, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => (string) config('banking.sber.client_id'),
            'client_secret' => $this->secrets->read(
                config('banking.sber.client_secret_file'),
                'Sber client secret'
            ),
        ]);
    }

    private function requestTokens(BankEnvironment $environment, array $form): BankTokensData
    {
        $payload = $this->client->request($environment, 'oauth.token', form: $form);

        if (! is_array($payload)) {
            throw new BankMalformedResponseException('Sber token response is malformed.', 'oauth.token');
        }

        $tokens = BankTokensData::fromSberResponse($payload);

        if ($tokens->accessToken === '' || $tokens->refreshToken === '') {
            throw new BankMalformedResponseException('Sber token response lacks required tokens.', 'oauth.token');
        }

        if (strcasecmp($tokens->tokenType, 'Bearer') !== 0) {
            throw new BankMalformedResponseException('Sber token response uses an unsupported token type.', 'oauth.token');
        }

        if ($tokens->scopes !== []) {
            foreach ($tokens->scopes as $scope) {
                if (! in_array($scope, self::ALLOWED_SCOPES, true)) {
                    throw new ReadOnlyViolationException("Sber returned a scope [{$scope}] that is not allowed in read-only mode.");
                }
            }

            foreach ($this->scopes() as $requiredScope) {
                if (! in_array($requiredScope, $tokens->scopes, true)) {
                    throw new BankAuthenticationException("Sber did not grant the required scope [{$requiredScope}].");
                }
            }
        }

        return $tokens;
    }

    private function scopes(): array
    {
        $configured = array_values(array_unique(array_map(
            'strval',
            (array) config('banking.sber.scopes', [])
        )));

        foreach ($configured as $scope) {
            if (! in_array($scope, self::ALLOWED_SCOPES, true)) {
                throw new ReadOnlyViolationException("Sber scope [{$scope}] is not allowed in read-only mode.");
            }
        }

        if (! in_array('openid', $configured, true) || ! in_array('GET_STATEMENT_ACCOUNT', $configured, true)) {
            throw new BankConfigurationException('Sber scopes must include openid and GET_STATEMENT_ACCOUNT.');
        }

        return $configured;
    }

    private function assertOAuthConfiguration(): void
    {
        if (! (bool) config('banking.enabled') || ! (bool) config('banking.sber.enabled')) {
            throw new BankConfigurationException('Sber API is disabled.');
        }

        if (! (bool) config('banking.sber.read_only')) {
            throw new ReadOnlyViolationException('SBER_READ_ONLY must remain true.');
        }

        foreach (['client_id', 'redirect_uri'] as $key) {
            if (trim((string) config("banking.sber.{$key}")) === '') {
                throw new BankConfigurationException("Sber {$key} is not configured.");
            }
        }

        $redirect = parse_url((string) config('banking.sber.redirect_uri'));

        if (
            ! is_array($redirect)
            || ($redirect['scheme'] ?? null) !== 'https'
            || empty($redirect['host'])
            || isset($redirect['user'])
            || isset($redirect['pass'])
            || isset($redirect['fragment'])
        ) {
            throw new BankConfigurationException('Sber redirect URI must be an absolute HTTPS URL.');
        }

        $this->scopes();
    }

    private function configuredDate(string $key): ?CarbonImmutable
    {
        $value = config($key);

        return is_string($value) && trim($value) !== ''
            ? CarbonImmutable::parse($value)
            : null;
    }

    private function certificateExpiry(): ?CarbonImmutable
    {
        $path = config('banking.sber.mtls_cert_path');
        $resolved = is_string($path) ? realpath($path) : false;

        if ($resolved === false || ! is_readable($resolved)) {
            return null;
        }

        $parsed = openssl_x509_parse((string) file_get_contents($resolved));
        $timestamp = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;

        return is_int($timestamp)
            ? CarbonImmutable::createFromTimestampUTC($timestamp)
            : null;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function safeCallbackError(?string $error, ?string $description): ?string
    {
        if ($error === null || $error === '') {
            return null;
        }

        $code = preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $error) === 1
            ? $error
            : 'oauth_error';

        return $description === null || $description === ''
            ? $code
            : $code.':description_sha256='.hash('sha256', $description);
    }
}
