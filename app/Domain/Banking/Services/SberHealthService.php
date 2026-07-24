<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Providers\Sber\SberReadOnlyApiClient;
use Carbon\CarbonImmutable;

class SberHealthService
{
    public function __construct(private readonly SberReadOnlyApiClient $client) {}

    public function inspect(): array
    {
        $checks = [];
        $checks[] = $this->check(
            'read_only',
            (bool) config('banking.sber.read_only'),
            'SBER_READ_ONLY is enabled.',
            'SBER_READ_ONLY must be true.'
        );
        $checks[] = $this->check(
            'api_enabled',
            (bool) config('banking.sber.enabled'),
            'Sber API is enabled.',
            'Sber API is disabled.',
            (bool) config('banking.sber.enabled') ? 'ok' : 'warning'
        );

        try {
            $environment = BankEnvironment::from((string) config('banking.sber.environment'));
            $checks[] = ['name' => 'environment', 'status' => 'ok', 'message' => "Environment: {$environment->value}."];

            foreach (['authorization', 'api'] as $baseType) {
                try {
                    $this->client->validatedBaseUrl($environment, $baseType);
                    $checks[] = [
                        'name' => "{$baseType}_base_url",
                        'status' => 'ok',
                        'message' => "The {$baseType} host matches the {$environment->value} allowlist.",
                    ];
                } catch (\Throwable) {
                    $checks[] = [
                        'name' => "{$baseType}_base_url",
                        'status' => 'error',
                        'message' => "The {$baseType} URL or environment host allowlist is invalid.",
                    ];
                }
            }
        } catch (\ValueError) {
            $checks[] = ['name' => 'environment', 'status' => 'error', 'message' => 'Sber environment is invalid.'];
        }

        foreach ([
            'client_id' => 'Client ID',
            'redirect_uri' => 'OAuth redirect URI',
            'client_secret_file' => 'Client secret file',
            'mtls_cert_path' => 'mTLS certificate',
            'mtls_key_path' => 'mTLS private key',
        ] as $key => $label) {
            $value = config("banking.sber.{$key}");
            $checks[] = $this->check(
                $key,
                is_string($value) && trim($value) !== '',
                "{$label} is configured.",
                "{$label} is not configured."
            );
        }

        $checks[] = $this->redirectUriCheck();
        $checks[] = $this->scopeCheck();
        $checks[] = $this->check(
            'jwt_issuer',
            trim((string) config('banking.sber.jwt_issuer')) !== '',
            'JWT issuer is configured.',
            'JWT issuer is not configured.'
        );
        $checks[] = $this->fileCheck(
            'client_secret_file',
            config('banking.sber.client_secret_file'),
            true,
            restrictedPermissions: true,
        );
        $checks[] = $this->fileCheck('mtls_cert_path', config('banking.sber.mtls_cert_path'), true);
        $checks[] = $this->privateKeyCheck();
        $checks[] = $this->fileCheck(
            'mtls_key_password_file',
            config('banking.sber.mtls_key_password_file'),
            true,
            optional: true,
            restrictedPermissions: true,
        );
        $checks[] = $this->fileCheck('ca_bundle_path', config('banking.sber.ca_bundle_path'), false, true);
        $checks[] = $this->fileCheck(
            'jwt_public_key_path',
            config('banking.sber.jwt_public_key_path'),
            false,
        );
        $checks[] = $this->certificateExpiryCheck();
        $checks[] = $this->configuredExpiryCheck(
            'client_secret_expiry',
            config('banking.sber.client_secret_expires_at'),
            'Client secret'
        );

        $statuses = array_column($checks, 'status');

        return [
            'status' => in_array('error', $statuses, true)
                ? 'error'
                : (in_array('warning', $statuses, true) ? 'warning' : 'ok'),
            'checks' => $checks,
        ];
    }

    public function expiringReasons(): array
    {
        $reasons = [];

        foreach ([
            'client_secret' => config('banking.sber.client_secret_expires_at'),
            'mtls_certificate' => $this->certificateExpiry(),
        ] as $name => $date) {
            if (! $date) {
                continue;
            }

            $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);
            $days = (int) round(now()->startOfDay()->diffInDays($date->startOfDay(), false));

            if (in_array($days, [30, 14, 7], true) || $days < 7) {
                $reasons[] = "{$name}_expires_in_{$days}_days";
            }
        }

        return $reasons;
    }

    private function fileCheck(
        string $name,
        mixed $path,
        bool $mustBeOutsideRepository,
        bool $optional = false,
        bool $restrictedPermissions = false,
    ): array {
        if (! is_string($path) || trim($path) === '') {
            return [
                'name' => $name,
                'status' => $optional ? 'ok' : 'error',
                'message' => $optional ? 'Optional file is not configured.' : 'Required file is not configured.',
            ];
        }

        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            return ['name' => $name, 'status' => 'error', 'message' => 'Configured file is unavailable.'];
        }

        if ($mustBeOutsideRepository && str_starts_with($resolved, base_path().DIRECTORY_SEPARATOR)) {
            return ['name' => $name, 'status' => 'error', 'message' => 'Secret file must be outside the repository.'];
        }

        if ($restrictedPermissions) {
            $permissions = fileperms($resolved);

            if ($permissions !== false && (($permissions & 0777) & 0077) !== 0) {
                return ['name' => $name, 'status' => 'error', 'message' => 'Secret-file permissions must be 0600 or stricter.'];
            }
        }

        return ['name' => $name, 'status' => 'ok', 'message' => 'Configured file is readable.'];
    }

    private function redirectUriCheck(): array
    {
        $parts = parse_url((string) config('banking.sber.redirect_uri'));
        $valid = is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment']);

        return $this->check(
            'redirect_uri_security',
            $valid,
            'OAuth redirect URI is an absolute HTTPS URL.',
            'OAuth redirect URI must be an absolute HTTPS URL.'
        );
    }

    private function scopeCheck(): array
    {
        $scopes = array_values(array_unique(array_map(
            'strval',
            (array) config('banking.sber.scopes', [])
        )));
        $allowed = ['openid', 'GET_STATEMENT_ACCOUNT'];
        $valid = $scopes !== []
            && array_diff($scopes, $allowed) === []
            && array_diff($allowed, $scopes) === [];

        return $this->check(
            'read_only_scopes',
            $valid,
            'Only the required read-only OAuth scopes are configured.',
            'OAuth scopes must be exactly openid and GET_STATEMENT_ACCOUNT.'
        );
    }

    private function privateKeyCheck(): array
    {
        $path = config('banking.sber.mtls_key_path');
        $base = $this->fileCheck('mtls_key_path_security', $path, true);

        if ($base['status'] !== 'ok') {
            return $base;
        }

        $permissions = fileperms((string) realpath((string) $path));
        $mode = $permissions === false ? null : ($permissions & 0777);

        return $mode !== null && ($mode & 0077) === 0
            ? ['name' => 'mtls_key_permissions', 'status' => 'ok', 'message' => 'Private-key permissions are restricted.']
            : ['name' => 'mtls_key_permissions', 'status' => 'error', 'message' => 'Private-key permissions must be 0600 or stricter.'];
    }

    private function certificateExpiryCheck(): array
    {
        $expiry = $this->certificateExpiry();

        if (! $expiry) {
            return ['name' => 'mtls_certificate_expiry', 'status' => 'error', 'message' => 'Certificate expiry could not be read.'];
        }

        $days = now()->startOfDay()->diffInDays($expiry->startOfDay(), false);

        return [
            'name' => 'mtls_certificate_expiry',
            'status' => $days < 0 ? 'error' : ($days <= 30 ? 'warning' : 'ok'),
            'message' => $days < 0
                ? 'mTLS certificate has expired.'
                : "mTLS certificate expires in {$days} days.",
            'expires_at' => $expiry->toDateString(),
        ];
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
        } catch (\Throwable) {
            return null;
        }

        $timestamp = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;

        return is_int($timestamp) ? CarbonImmutable::createFromTimestampUTC($timestamp) : null;
    }

    private function configuredExpiryCheck(string $name, mixed $value, string $label): array
    {
        if (! is_string($value) || trim($value) === '') {
            return ['name' => $name, 'status' => 'warning', 'message' => "{$label} expiry is not configured."];
        }

        try {
            $expiry = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return ['name' => $name, 'status' => 'error', 'message' => "{$label} expiry is invalid."];
        }

        $days = now()->startOfDay()->diffInDays($expiry->startOfDay(), false);

        return [
            'name' => $name,
            'status' => $days < 0 ? 'error' : ($days <= 30 ? 'warning' : 'ok'),
            'message' => $days < 0 ? "{$label} has expired." : "{$label} expires in {$days} days.",
            'expires_at' => $expiry->toDateString(),
        ];
    }

    private function check(
        string $name,
        bool $condition,
        string $success,
        string $failure,
        string $failureStatus = 'error',
    ): array {
        return [
            'name' => $name,
            'status' => $condition ? 'ok' : $failureStatus,
            'message' => $condition ? $success : $failure,
        ];
    }
}
