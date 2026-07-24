<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankEnvironment;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankAuthorizationException;
use App\Domain\Banking\Exceptions\BankCertificateException;
use App\Domain\Banking\Exceptions\BankConfigurationException;
use App\Domain\Banking\Exceptions\BankNetworkTimeoutException;
use App\Domain\Banking\Exceptions\BankRateLimitException;
use App\Domain\Banking\Exceptions\BankUnavailableException;
use App\Domain\Banking\Exceptions\BankValidationException;
use App\Domain\Banking\Exceptions\ReadOnlyViolationException;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Models\BankConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SberReadOnlyApiClient
{
    /**
     * This is the complete outbound allowlist. No payment, signature, file
     * upload, token revocation or client-secret mutation endpoint is present.
     */
    private const ENDPOINTS = [
        'oauth.token' => [
            'method' => 'POST',
            'base' => 'authorization',
            'path' => '/ic/sso/api/v2/oauth/token',
            'auth' => false,
            'response' => 'json',
        ],
        'oauth.user_info' => [
            'method' => 'GET',
            'base' => 'authorization',
            'path' => '/ic/sso/api/v2/oauth/user-info',
            'auth' => true,
            'response' => 'json_or_jwt',
        ],
        'statement.daily' => [
            'method' => 'GET',
            'base' => 'api',
            'path' => '/fintech/api/v2/statement/transactions',
            'auth' => true,
            'response' => 'json',
        ],
        'statement.summary' => [
            'method' => 'GET',
            'base' => 'api',
            'path' => '/fintech/api/v2/statement/summary',
            'auth' => true,
            'response' => 'json',
        ],
        'statement.increment' => [
            'method' => 'GET',
            'base' => 'api',
            'path' => '/fintech/api/v2/statement/increment',
            'auth' => true,
            'response' => 'json',
        ],
        'statement.transaction' => [
            'method' => 'GET',
            'base' => 'api',
            'path' => '/fintech/api/v2/statement/transactionId',
            'auth' => true,
            'response' => 'json',
        ],
    ];

    public function __construct(
        private readonly BankAuditLogger $audit,
        private readonly JsonNumbersAsStringsDecoder $json,
    ) {}

    /**
     * @return array<string, mixed>|string
     */
    public function request(
        BankEnvironment|string $environment,
        string $endpointAlias,
        array $query = [],
        array $form = [],
        ?BankConnection $connection = null,
        array $pathParameters = [],
    ): array|string {
        $this->assertOperational();
        $environment = $environment instanceof BankEnvironment
            ? $environment
            : BankEnvironment::from($environment);
        $endpoint = self::ENDPOINTS[$endpointAlias] ?? null;

        if ($endpoint === null) {
            throw new ReadOnlyViolationException("Unknown Sber endpoint alias [{$endpointAlias}] was rejected.");
        }

        $path = $this->resolvePath($endpoint['path'], $pathParameters);
        $this->assertAllowed($endpoint['method'], $path);

        if ($endpoint['auth'] && ! $connection) {
            throw new BankConfigurationException('An active bank connection is required for this endpoint.');
        }

        $token = null;

        if ($endpoint['auth']) {
            $token = app(SberTokenManager::class)->accessTokenFor($connection);
        }

        $response = $this->send(
            environment: $environment,
            endpointAlias: $endpointAlias,
            endpoint: $endpoint,
            path: $path,
            query: $query,
            form: $form,
            accessToken: $token,
        );

        if ($response->status() === 401 && $endpoint['auth']) {
            $token = app(SberTokenManager::class)->refreshAfterUnauthorized($connection, (string) $token);
            $response = $this->send(
                environment: $environment,
                endpointAlias: $endpointAlias,
                endpoint: $endpoint,
                path: $path,
                query: $query,
                form: $form,
                accessToken: $token,
            );

            if ($response->status() === 401) {
                $connection->forceFill([
                    'status' => BankConnectionStatus::ReauthorizationRequired,
                    'last_error_at' => now(),
                ])->save();
                $this->audit->record('bank.connection.reauthorization_required', $connection, [
                    'endpoint_alias' => $endpointAlias,
                    'reason' => 'repeated_401',
                ]);
                BankConnectionRequiresAttention::dispatch(
                    $connection->fresh(),
                    'reauthorization_required'
                );
            }
        }

        $this->throwForResponse($response, $endpointAlias);

        return $this->decodeSuccess($response, $endpoint['response'], $endpointAlias);
    }

    public function assertAllowed(string $method, string $pathOrUrl): void
    {
        $method = strtoupper(trim($method));
        $path = parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl;

        foreach (self::ENDPOINTS as $endpoint) {
            if ($method !== $endpoint['method']) {
                continue;
            }

            $pattern = preg_quote($endpoint['path'], '#');
            if (preg_match('#^'.$pattern.'$#', $path) === 1) {
                return;
            }
        }

        throw new ReadOnlyViolationException("Sber {$method} {$path} is not in the read-only allowlist.");
    }

    public function validatedBaseUrl(
        BankEnvironment|string $environment,
        string $type,
    ): string {
        $environment = $environment instanceof BankEnvironment
            ? $environment
            : BankEnvironment::from($environment);

        if (! in_array($type, ['authorization', 'api'], true)) {
            throw new BankConfigurationException('Unknown Sber base URL type.');
        }

        return $this->baseUrl($environment, $type);
    }

    /**
     * @return array<string, string|int>
     */
    public function validatePaginationUrl(
        BankEnvironment|string $environment,
        string $endpointAlias,
        string $url,
    ): array {
        $environment = $environment instanceof BankEnvironment
            ? $environment
            : BankEnvironment::from($environment);
        $endpoint = self::ENDPOINTS[$endpointAlias] ?? null;

        if (! $endpoint || $endpoint['method'] !== 'GET') {
            throw new ReadOnlyViolationException('Pagination endpoint is not allowlisted.');
        }

        $configuredBase = $this->baseUrl($environment, $endpoint['base']);

        if (str_starts_with($url, '?')) {
            $url = $configuredBase.$endpoint['path'].$url;
        } elseif (str_starts_with($url, '/')) {
            $url = $configuredBase.$url;
        } elseif (! str_starts_with($url, 'https://')) {
            throw new ReadOnlyViolationException('Sber pagination URL is invalid.');
        }

        $expected = parse_url($configuredBase);
        $actual = parse_url($url);

        if (
            ! is_array($expected)
            || ! is_array($actual)
            || ($actual['scheme'] ?? null) !== 'https'
            || isset($actual['user'])
            || isset($actual['pass'])
            || isset($actual['fragment'])
        ) {
            throw new ReadOnlyViolationException('Sber pagination URL host was rejected.');
        }

        $expectedPort = $expected['port'] ?? 443;
        $actualPort = $actual['port'] ?? 443;
        $expectedHost = mb_strtolower((string) ($expected['host'] ?? ''));
        $actualHost = mb_strtolower((string) ($actual['host'] ?? ''));

        if (! hash_equals($expectedHost, $actualHost) || $expectedPort !== $actualPort) {
            throw new ReadOnlyViolationException('Sber pagination URL host was rejected.');
        }

        $this->assertAllowed('GET', (string) ($actual['path'] ?? ''));

        $expectedPath = $this->resolvePath($endpoint['path'], []);

        if (($actual['path'] ?? '') !== $expectedPath) {
            throw new ReadOnlyViolationException('Sber pagination URL points to another endpoint.');
        }

        parse_str((string) ($actual['query'] ?? ''), $query);
        $allowedQuery = [
            'accountNumber',
            'statementDate',
            'lastModifyDate',
            'lastModifyDateTo',
            'page',
            'format',
            'curFormat',
            'id',
        ];

        foreach (array_keys($query) as $key) {
            if (! in_array((string) $key, $allowedQuery, true) || is_array($query[$key])) {
                throw new ReadOnlyViolationException('Sber pagination URL contains an unexpected query parameter.');
            }
        }

        return $query;
    }

    private function send(
        BankEnvironment $environment,
        string $endpointAlias,
        array $endpoint,
        string $path,
        array $query,
        array $form,
        ?string $accessToken,
    ): Response {
        $requestId = (string) Str::uuid();
        $request = $this->pendingRequest()
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Request-ID' => $requestId,
                'RqUID' => $requestId,
            ]);

        if ($accessToken !== null && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        $url = $this->baseUrl($environment, $endpoint['base']).$path;

        try {
            return $endpoint['method'] === 'GET'
                ? $request->get($url, $query)
                : $request->asForm()->post($url, $form);
        } catch (ConnectionException $exception) {
            Log::channel('banking')->warning('Sber request failed at the transport layer.', [
                'endpoint_alias' => $endpointAlias,
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);

            $message = strtolower($exception->getMessage());

            if (str_contains($message, 'certificate') || str_contains($message, 'ssl')) {
                throw new BankCertificateException('Sber mTLS connection failed.');
            }

            throw new BankNetworkTimeoutException(endpoint: $endpointAlias);
        }
    }

    private function pendingRequest(): PendingRequest
    {
        $certificate = $this->requiredReadableFile(
            config('banking.sber.mtls_cert_path'),
            'Sber mTLS certificate',
            mustBeOutsideRepository: true,
        );
        $privateKey = $this->requiredReadableFile(
            config('banking.sber.mtls_key_path'),
            'Sber mTLS private key',
            mustBeOutsideRepository: true,
            restrictedPermissions: true,
        );
        $passwordFile = config('banking.sber.mtls_key_password_file');
        $password = null;

        if (is_string($passwordFile) && trim($passwordFile) !== '') {
            $password = app(SecretFileReader::class)->read($passwordFile, 'Sber mTLS key password');
        }

        $caBundle = config('banking.sber.ca_bundle_path');
        $verify = true;

        if (is_string($caBundle) && trim($caBundle) !== '') {
            $verify = $this->requiredReadableFile($caBundle, 'Sber CA bundle');
        }

        return Http::timeout((int) config('banking.sber.request_timeout', 30))
            ->connectTimeout((int) config('banking.sber.connect_timeout', 10))
            ->withOptions([
                'cert' => $certificate,
                'ssl_key' => $password === null ? $privateKey : [$privateKey, $password],
                'verify' => $verify,
                'allow_redirects' => false,
            ]);
    }

    private function throwForResponse(Response $response, string $endpointAlias): void
    {
        if ($response->status() !== 202 && $response->successful()) {
            return;
        }

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $cause = $this->safeCause($payload['cause'] ?? $payload['error'] ?? null);
        $message = $this->safeScalar($payload['message'] ?? $payload['error_description'] ?? null);
        $status = $response->status();

        if ($status === 401) {
            throw new BankAuthenticationException('Sber access token was rejected.', $cause, $endpointAlias);
        }

        if ($status === 403) {
            throw new BankAuthorizationException('Sber API scope or account access is insufficient.', $cause, $endpointAlias);
        }

        if ($status === 429) {
            throw new BankRateLimitException(
                $this->retryAfterSeconds($response->header('Retry-After')),
                $cause,
                $endpointAlias
            );
        }

        if ($status === 202 || $status >= 500) {
            throw new BankUnavailableException(
                $status === 202 ? 'Sber statement is still being prepared.' : 'Sber API is temporarily unavailable.',
                $status,
                $cause,
                $endpointAlias,
            );
        }

        throw new BankValidationException(
            $this->safeErrorMessage($message, $status),
            $status,
            $cause,
            $endpointAlias,
        );
    }

    private function decodeSuccess(Response $response, string $type, string $endpointAlias): array|string
    {
        $body = trim($response->body());

        if ($type === 'json_or_jwt' && substr_count($body, '.') === 2 && ! str_starts_with($body, '{')) {
            return $body;
        }

        return $this->json->decode($body, $endpointAlias);
    }

    private function resolvePath(string $template, array $parameters): string
    {
        if ($parameters !== []) {
            throw new ReadOnlyViolationException('Path parameters are not allowed by the Sber read-only endpoint map.');
        }

        return $template;
    }

    private function baseUrl(BankEnvironment $environment, string $type): string
    {
        $key = $type === 'authorization' ? 'authorization_base_url' : 'api_base_url';
        $url = rtrim((string) config("banking.sber.environments.{$environment->value}.{$key}"), '/');
        $parts = parse_url($url);
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $globalHosts = array_map(
            'mb_strtolower',
            (array) config('banking.sber.allowed_hosts', [])
        );
        $environmentHosts = array_map(
            'mb_strtolower',
            (array) config(
                "banking.sber.environments.{$environment->value}.{$type}_hosts",
                []
            )
        );

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array((string) ($parts['path'] ?? ''), ['', '/'], true)
            || ! in_array($host, $globalHosts, true)
            || ! in_array($host, $environmentHosts, true)
        ) {
            throw new BankConfigurationException("Sber {$type} base URL is invalid.");
        }

        return $url;
    }

    private function assertOperational(): void
    {
        if (! (bool) config('banking.enabled') || ! (bool) config('banking.sber.enabled')) {
            throw new BankConfigurationException('Sber API is disabled.');
        }

        if (! (bool) config('banking.sber.read_only')) {
            throw new ReadOnlyViolationException('SBER_READ_ONLY must remain true.');
        }
    }

    private function requiredReadableFile(
        mixed $path,
        string $label,
        bool $mustBeOutsideRepository = false,
        bool $restrictedPermissions = false,
    ): string {
        if (! is_string($path) || trim($path) === '') {
            throw new BankConfigurationException("{$label} path is not configured.");
        }

        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new BankConfigurationException("{$label} is unavailable.");
        }

        if (
            $mustBeOutsideRepository
            && str_starts_with($resolved, base_path().DIRECTORY_SEPARATOR)
        ) {
            throw new BankConfigurationException("{$label} must be outside the repository.");
        }

        if ($restrictedPermissions) {
            $permissions = fileperms($resolved);

            if ($permissions !== false && (($permissions & 0777) & 0077) !== 0) {
                throw new BankConfigurationException("{$label} permissions must be 0600 or stricter.");
            }
        }

        return $resolved;
    }

    private function safeScalar(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return mb_substr((string) $value, 0, 512);
    }

    private function safeCause(mixed $value): ?string
    {
        $value = $this->safeScalar($value);

        return $value !== null && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $value) === 1
            ? $value
            : null;
    }

    private function retryAfterSeconds(?string $value): int
    {
        $value = trim((string) $value);

        if ($value !== '' && ctype_digit($value)) {
            return max(1, (int) $value);
        }

        $timestamp = $value !== '' ? strtotime($value) : false;

        return $timestamp === false ? 60 : max(1, $timestamp - time());
    }

    private function safeErrorMessage(?string $message, int $status): string
    {
        if ($message === null || $message === '') {
            return "Sber API request failed with HTTP {$status}.";
        }

        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED]', $message) ?? $message;
        $message = preg_replace('/\b\d{16,34}\b/u', '[REDACTED_NUMBER]', $message) ?? $message;
        $message = preg_replace(
            '/\b(access_token|refresh_token|id_token|client_secret)\s*[:=]\s*[^\s,;]+/iu',
            '$1=[REDACTED]',
            $message
        ) ?? $message;

        return mb_substr($message, 0, 512);
    }
}
