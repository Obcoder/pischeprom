<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\DTO\BankBalanceData;
use App\Domain\Banking\DTO\BankStatementData;
use App\Domain\Banking\DTO\BankTokensData;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\Entity;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;

class SberBankProvider implements BankProviderInterface
{
    public function __construct(
        private readonly SberOAuthService $oauth,
        private readonly SberTokenManager $tokens,
        private readonly SberReadOnlyApiClient $client,
        private readonly SberResponseNormalizer $normalizer,
        private readonly SberStatementService $statements,
        private readonly SberIdTokenValidator $idTokens,
    ) {}

    public function getAuthorizationUrl(User $user, ?Entity $ownerEntity = null): string
    {
        return $this->oauth->authorizationUrl($user, $ownerEntity);
    }

    public function exchangeAuthorizationCode(
        string $code,
        string $state,
        ?string $error = null,
        ?string $errorDescription = null,
    ): BankConnection {
        return $this->oauth->exchangeAuthorizationCode($code, $state, $error, $errorDescription);
    }

    public function refreshTokens(BankConnection $connection): BankTokensData
    {
        return $this->tokens->refreshTokens($connection);
    }

    public function getAccounts(BankConnection $connection): Collection
    {
        $payload = $this->client->request(
            $connection->environment,
            'oauth.user_info',
            connection: $connection,
        );

        if (is_string($payload)) {
            $payload = $this->idTokens->validate($payload, requireNonce: false);
        }

        if (! is_array($payload)) {
            throw new BankMalformedResponseException('Sber user-info response is malformed.', 'oauth.user_info');
        }

        return $this->normalizer->accounts($payload);
    }

    public function getDailyBalance(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): ?BankBalanceData {
        return $this->statements->summary($connection, $account, $date);
    }

    public function getDailyStatement(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): BankStatementData {
        return $this->statements->daily($connection, $account, $date);
    }

    public function getIncrementalStatement(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $lastModifyDate,
        ?DateTimeInterface $lastModifyDateTo = null,
    ): BankStatementData {
        return $this->statements->incremental($connection, $account, $lastModifyDate, $lastModifyDateTo);
    }

    public function getTransaction(
        BankConnection $connection,
        BankAccount $account,
        string $operationId,
        DateTimeInterface $statementDate,
    ): \App\Domain\Banking\DTO\BankTransactionData {
        return $this->statements->transaction($connection, $account, $operationId, $statementDate);
    }
}
