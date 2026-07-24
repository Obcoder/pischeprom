<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\DTO\BankTokensData;
use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankAuthorizationException;
use App\Domain\Banking\Exceptions\BankUnavailableException;
use App\Domain\Banking\Exceptions\BankValidationException;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Models\BankConnection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SberTokenManager
{
    public function __construct(
        private readonly SberOAuthService $oauth,
        private readonly BankAuditLogger $audit,
    ) {}

    public function accessTokenFor(BankConnection $connection): string
    {
        $connection->refresh();
        $leeway = (int) config('banking.sber.token_refresh_leeway_seconds', 300);

        if (
            $connection->access_token
            && $connection->access_token_expires_at
            && $connection->access_token_expires_at->isAfter(now()->addSeconds($leeway))
        ) {
            return $connection->access_token;
        }

        return $this->refreshWithLock($connection, $connection->access_token);
    }

    public function refreshAfterUnauthorized(BankConnection $connection, string $tokenUsed): string
    {
        return $this->refreshWithLock($connection, $tokenUsed, true);
    }

    public function refreshTokens(BankConnection $connection): BankTokensData
    {
        $token = $connection->access_token;
        $this->refreshWithLock($connection, $token, true);
        $connection->refresh();

        return new BankTokensData(
            accessToken: (string) $connection->access_token,
            refreshToken: (string) $connection->refresh_token,
            accessTokenExpiresAt: $connection->access_token_expires_at->toImmutable(),
            refreshTokenExpiresAt: $connection->refresh_token_expires_at->toImmutable(),
            scopes: $connection->scopes ?? [],
        );
    }

    private function refreshWithLock(
        BankConnection $connection,
        ?string $tokenObserved,
        bool $forceIfSame = false,
    ): string {
        $lock = Cache::store((string) config('banking.lock_store', 'redis'))
            ->lock("banking:sber:token:{$connection->getKey()}", 30);

        try {
            return $lock->block(10, function () use ($connection, $tokenObserved, $forceIfSame): string {
                /** @var BankConnection $fresh */
                $fresh = BankConnection::query()->findOrFail($connection->getKey());
                $leeway = (int) config('banking.sber.token_refresh_leeway_seconds', 300);
                $anotherWorkerRefreshed = $tokenObserved !== null
                    && $fresh->access_token !== null
                    && ! hash_equals($tokenObserved, $fresh->access_token);
                $stillValid = $fresh->access_token_expires_at?->isAfter(now()->addSeconds($leeway)) ?? false;

                if ($anotherWorkerRefreshed && $stillValid) {
                    return $fresh->access_token;
                }

                if (! $forceIfSame && $stillValid && $fresh->access_token) {
                    return $fresh->access_token;
                }

                try {
                    $tokens = $this->oauth->refresh($fresh);
                } catch (Throwable $exception) {
                    $requiresReauthorization = $this->requiresReauthorization($exception);

                    DB::transaction(function () use ($fresh, $requiresReauthorization): void {
                        BankConnection::query()
                            ->whereKey($fresh->getKey())
                            ->lockForUpdate()
                            ->firstOrFail()
                            ->forceFill([
                                'status' => $requiresReauthorization
                                    ? BankConnectionStatus::ReauthorizationRequired
                                    : BankConnectionStatus::Error,
                                'last_error_at' => now(),
                            ])
                            ->save();
                    }, 3);
                    $fresh->refresh();
                    $reason = $requiresReauthorization
                        ? 'reauthorization_required'
                        : 'token_refresh_failed';
                    $this->audit->record("bank.connection.{$reason}", $fresh, [
                        'reason' => $reason,
                        'exception' => $exception::class,
                    ]);
                    BankConnectionRequiresAttention::dispatch($fresh, $reason);

                    throw $exception;
                }

                DB::transaction(function () use ($fresh, $tokens): void {
                    $locked = BankConnection::query()
                        ->whereKey($fresh->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $locked->forceFill([
                        'access_token' => $tokens->accessToken,
                        'refresh_token' => $tokens->refreshToken,
                        'access_token_expires_at' => $tokens->accessTokenExpiresAt,
                        'refresh_token_expires_at' => $tokens->refreshTokenExpiresAt,
                        'scopes' => $tokens->scopes ?: $locked->scopes,
                        'status' => BankConnectionStatus::Active,
                        'last_error_at' => null,
                    ])->save();
                }, 3);

                $this->audit->record('bank.connection.tokens_refreshed', $fresh, [
                    'access_token_expires_at' => $tokens->accessTokenExpiresAt->toISOString(),
                    'refresh_token_expires_at' => $tokens->refreshTokenExpiresAt->toISOString(),
                ]);

                return $tokens->accessToken;
            });
        } catch (LockTimeoutException $exception) {
            throw new BankUnavailableException(
                'Timed out waiting for the Sber token refresh lock.',
                503,
                endpoint: 'oauth.token',
            );
        }
    }

    private function requiresReauthorization(Throwable $exception): bool
    {
        return $exception instanceof BankAuthenticationException
            || $exception instanceof BankAuthorizationException
            || (
                $exception instanceof BankValidationException
                && $exception->endpointAlias === 'oauth.token'
            );
    }
}
