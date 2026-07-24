<?php

namespace App\Domain\Banking\DTO;

use Carbon\CarbonImmutable;

final readonly class BankTokensData
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonImmutable $accessTokenExpiresAt,
        public CarbonImmutable $refreshTokenExpiresAt,
        public array $scopes,
        public ?string $idToken = null,
        public string $tokenType = 'Bearer',
    ) {}

    public static function fromSberResponse(array $payload): self
    {
        $expiresIn = max(1, (int) ($payload['expires_in'] ?? 3600));
        $refreshExpiresIn = max(1, (int) ($payload['refresh_expires_in'] ?? (180 * 24 * 60 * 60)));
        $scope = $payload['scope'] ?? [];

        if (is_string($scope)) {
            $scope = preg_split('/[\s,]+/', trim($scope)) ?: [];
        }

        return new self(
            accessToken: (string) ($payload['access_token'] ?? ''),
            refreshToken: (string) ($payload['refresh_token'] ?? ''),
            accessTokenExpiresAt: CarbonImmutable::now()->addSeconds($expiresIn),
            refreshTokenExpiresAt: CarbonImmutable::now()->addSeconds($refreshExpiresIn),
            scopes: array_values(array_filter(array_map('strval', is_array($scope) ? $scope : []))),
            idToken: isset($payload['id_token']) ? (string) $payload['id_token'] : null,
            tokenType: (string) ($payload['token_type'] ?? 'Bearer'),
        );
    }
}
