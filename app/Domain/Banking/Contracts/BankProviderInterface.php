<?php

namespace App\Domain\Banking\Contracts;

use App\Domain\Banking\DTO\BankBalanceData;
use App\Domain\Banking\DTO\BankStatementData;
use App\Domain\Banking\DTO\BankTokensData;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\Entity;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;

interface BankProviderInterface
{
    public function getAuthorizationUrl(User $user, ?Entity $ownerEntity = null): string;

    public function exchangeAuthorizationCode(
        string $code,
        string $state,
        ?string $error = null,
        ?string $errorDescription = null,
    ): BankConnection;

    public function refreshTokens(BankConnection $connection): BankTokensData;

    /** @return Collection<int, \App\Domain\Banking\DTO\BankAccountData> */
    public function getAccounts(BankConnection $connection): Collection;

    public function getDailyBalance(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): ?BankBalanceData;

    public function getDailyStatement(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): BankStatementData;

    public function getIncrementalStatement(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $lastModifyDate,
        ?DateTimeInterface $lastModifyDateTo = null,
    ): BankStatementData;

    public function getTransaction(
        BankConnection $connection,
        BankAccount $account,
        string $operationId,
        DateTimeInterface $statementDate,
    ): \App\Domain\Banking\DTO\BankTransactionData;
}
