<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Enums\BankProvider;
use App\Domain\Banking\Exceptions\BankAuthorizationException;
use App\Domain\Banking\Exceptions\BankConfigurationException;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Models\BankConnection;
use App\Models\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SberSandboxTokenImporter
{
    private const READ_ONLY_SCOPES = [
        'openid',
        'GET_STATEMENT_ACCOUNT',
    ];

    private const MAX_TOKEN_BYTES = 16384;

    public function __construct(
        private readonly SecretFileReader $secrets,
        private readonly BankAuditLogger $audit,
    ) {}

    public function import(
        Entity $owner,
        User $actor,
        string $accessTokenFile,
        string $refreshTokenFile,
        CarbonImmutable $accessTokenExpiresAt,
        CarbonImmutable $refreshTokenExpiresAt,
        bool $replace = false,
    ): BankConnection {
        $this->assertConfiguration($actor);

        if ($accessTokenExpiresAt->isPast()) {
            throw new BankConfigurationException('Sandbox access token expiry must be in the future.');
        }

        if ($refreshTokenExpiresAt->lessThanOrEqualTo($accessTokenExpiresAt)) {
            throw new BankConfigurationException('Sandbox refresh token must expire after the access token.');
        }

        $accessToken = $this->readToken($accessTokenFile, 'Sber sandbox access token');
        $refreshToken = $this->readToken($refreshTokenFile, 'Sber sandbox refresh token');

        if (hash_equals($accessToken, $refreshToken)) {
            throw new BankConfigurationException('Sandbox access and refresh tokens must be different.');
        }

        $connection = DB::transaction(function () use (
            $owner,
            $actor,
            $accessToken,
            $refreshToken,
            $accessTokenExpiresAt,
            $refreshTokenExpiresAt,
            $replace,
        ): BankConnection {
            $connection = BankConnection::query()
                ->where('provider', BankProvider::Sber->value)
                ->where('environment', BankEnvironment::Sandbox->value)
                ->where('owner_entity_id', $owner->id)
                ->lockForUpdate()
                ->first();

            if ($connection && ! $replace) {
                throw new BankConfigurationException(
                    'A sandbox connection already exists for this organization; use --replace explicitly.'
                );
            }

            $connection ??= new BankConnection;
            $connection->forceFill([
                'provider' => BankProvider::Sber,
                'owner_entity_id' => $owner->id,
                'environment' => BankEnvironment::Sandbox,
                'status' => BankConnectionStatus::Active,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => $accessTokenExpiresAt,
                'refresh_token_expires_at' => $refreshTokenExpiresAt,
                'scopes' => self::READ_ONLY_SCOPES,
                'connected_by' => $actor->id,
                'connected_at' => now(),
                'last_error_at' => null,
                'client_secret_expires_at' => $this->configuredDate(
                    'banking.sber.client_secret_expires_at'
                ),
                'mtls_certificate_expires_at' => $this->certificateExpiry(),
                'settings' => [
                    ...($connection->settings ?? []),
                    'token_source' => 'sandbox_personal_area',
                    'token_imported_at' => now()->toISOString(),
                ],
            ])->save();

            $this->audit->record('bank.connection.sandbox_tokens_imported', $connection, [
                'provider' => BankProvider::Sber->value,
                'environment' => BankEnvironment::Sandbox->value,
                'scopes' => self::READ_ONLY_SCOPES,
                'replaced_existing_connection' => $replace,
            ], $actor);

            return $connection->fresh();
        }, 3);

        return $connection;
    }

    private function assertConfiguration(User $actor): void
    {
        if ((string) config('banking.sber.environment') !== BankEnvironment::Sandbox->value) {
            throw new BankConfigurationException('Sandbox tokens can only be imported in the sandbox environment.');
        }

        if (! (bool) config('banking.sber.read_only')) {
            throw new BankConfigurationException('SBER_READ_ONLY must remain true.');
        }

        if (trim((string) config('banking.sber.client_id')) === '') {
            throw new BankConfigurationException('Sber client ID must be configured before token import.');
        }

        $scopes = array_values(array_unique(array_map(
            'strval',
            (array) config('banking.sber.scopes', [])
        )));
        sort($scopes);
        $requiredScopes = self::READ_ONLY_SCOPES;
        sort($requiredScopes);

        if ($scopes !== $requiredScopes) {
            throw new BankConfigurationException(
                'OAuth scopes must be exactly openid and GET_STATEMENT_ACCOUNT.'
            );
        }

        try {
            $isAdministrator = $actor->hasRole('admin', 'crm');
        } catch (Throwable) {
            $isAdministrator = false;
        }

        if (! $isAdministrator || ($actor->status ?? 'active') === 'blocked') {
            throw new BankAuthorizationException('Only an active CRM administrator may import sandbox tokens.');
        }

        // Refreshing a pair imported from the personal area still requires
        // the client secret. Read and immediately discard it to validate the
        // protected file before accepting the token pair.
        $this->secrets->read(
            config('banking.sber.client_secret_file'),
            'Sber client secret'
        );
    }

    private function readToken(string $path, string $label): string
    {
        $token = $this->secrets->read($path, $label);

        if (
            strlen($token) > self::MAX_TOKEN_BYTES
            || preg_match('/[\x00-\x20\x7F]/', $token) === 1
        ) {
            throw new BankConfigurationException("{$label} has an invalid format.");
        }

        return $token;
    }

    private function configuredDate(string $key): ?CarbonImmutable
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw new BankConfigurationException('Sber client secret expiry is invalid.');
        }
    }

    private function certificateExpiry(): ?CarbonImmutable
    {
        $path = config('banking.sber.mtls_cert_path');
        $resolved = is_string($path) ? realpath($path) : false;

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            return null;
        }

        try {
            $contents = file_get_contents($resolved);
            $parsed = is_string($contents) && $contents !== ''
                ? openssl_x509_parse($contents)
                : false;
        } catch (Throwable) {
            return null;
        }

        $timestamp = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;

        return is_int($timestamp)
            ? CarbonImmutable::createFromTimestampUTC($timestamp)
            : null;
    }
}
