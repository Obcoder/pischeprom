<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankConfigurationException;
use JsonException;

class SberIdTokenValidator
{
    public function __construct(private readonly SecretFileReader $secrets) {}

    public function validate(
        string $jwt,
        ?string $expectedNonceHash = null,
        bool $requireNonce = true,
    ): array {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new BankAuthenticationException('Sber ID token has an invalid JWS structure.');
        }

        try {
            $header = json_decode($this->base64UrlDecode($parts[0]), true, 32, JSON_THROW_ON_ERROR);
            $claims = json_decode($this->base64UrlDecode($parts[1]), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BankAuthenticationException('Sber ID token contains invalid JSON.');
        }

        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256') {
            throw new BankAuthenticationException('Sber ID token uses an unsupported signature algorithm.');
        }

        $publicKeyPath = config('banking.sber.jwt_public_key_path');
        $publicKey = $this->secrets->read(
            $publicKeyPath,
            'Sber JWT public key',
            mustBeOutsideRepository: false,
        );
        $key = openssl_pkey_get_public($publicKey);

        if ($key === false) {
            throw new BankConfigurationException('Sber JWT public key is invalid.');
        }

        $signature = $this->base64UrlDecode($parts[2]);
        $verified = openssl_verify($parts[0].'.'.$parts[1], $signature, $key, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new BankAuthenticationException('Sber ID token signature verification failed.');
        }

        $issuer = trim((string) config('banking.sber.jwt_issuer'));
        $clientId = trim((string) config('banking.sber.client_id'));

        if ($issuer === '' || $clientId === '') {
            throw new BankConfigurationException('Sber JWT issuer and client ID must be configured.');
        }

        if (! hash_equals($issuer, (string) ($claims['iss'] ?? ''))) {
            throw new BankAuthenticationException('Sber ID token issuer is invalid.');
        }

        $audience = $claims['aud'] ?? null;
        $audienceMatches = is_array($audience)
            ? in_array($clientId, array_map('strval', $audience), true)
            : hash_equals($clientId, (string) $audience);

        if (! $audienceMatches) {
            throw new BankAuthenticationException('Sber ID token audience is invalid.');
        }

        $now = time();

        if (! isset($claims['exp']) || (int) $claims['exp'] <= $now) {
            throw new BankAuthenticationException('Sber ID token has expired.');
        }

        if (isset($claims['iat']) && (int) $claims['iat'] > ($now + 60)) {
            throw new BankAuthenticationException('Sber ID token issue time is invalid.');
        }

        if ($requireNonce || $expectedNonceHash !== null) {
            $nonce = (string) ($claims['nonce'] ?? '');

            if ($nonce === '' || $expectedNonceHash === null || ! hash_equals($expectedNonceHash, hash('sha256', $nonce))) {
                throw new BankAuthenticationException('Sber ID token nonce is invalid.');
            }
        }

        return $claims;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        if ($decoded === false) {
            throw new BankAuthenticationException('Sber ID token contains invalid Base64URL data.');
        }

        return $decoded;
    }
}
