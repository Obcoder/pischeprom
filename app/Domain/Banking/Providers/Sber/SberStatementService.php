<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\DTO\BankBalanceData;
use App\Domain\Banking\DTO\BankStatementData;
use App\Domain\Banking\DTO\BankTransactionData;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Domain\Banking\Exceptions\BankValidationException;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class SberStatementService
{
    private const MAX_PAGES = 1000;

    public function __construct(
        private readonly SberReadOnlyApiClient $client,
        private readonly SberResponseNormalizer $normalizer,
    ) {}

    public function daily(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): BankStatementData {
        return $this->loadPages(
            connection: $connection,
            account: $account,
            endpointAlias: 'statement.daily',
            initialQuery: [
                'accountNumber' => $account->account_number,
                'statementDate' => CarbonImmutable::instance($date)->format('Y-m-d'),
                'page' => 1,
            ],
        );
    }

    public function summary(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $date,
    ): ?BankBalanceData {
        try {
            $payload = $this->client->request(
                $connection->environment,
                'statement.summary',
                query: [
                    'accountNumber' => $account->account_number,
                    'statementDate' => CarbonImmutable::instance($date)->format('Y-m-d'),
                ],
                connection: $connection,
            );
        } catch (BankValidationException $exception) {
            if (
                $exception->httpStatus === 404
                && in_array($exception->bankCause, ['DATA_NOT_FOUND', 'DATA_NOT_FOUND_EXCEPTION'], true)
            ) {
                return null;
            }

            throw $exception;
        }

        if (! is_array($payload)) {
            throw new BankMalformedResponseException(
                'Sber statement summary response is malformed.',
                'statement.summary'
            );
        }

        return $this->normalizer->balanceSummary($payload, $date);
    }

    public function incremental(
        BankConnection $connection,
        BankAccount $account,
        DateTimeInterface $lastModifyDate,
        ?DateTimeInterface $lastModifyDateTo = null,
    ): BankStatementData {
        $cursor = CarbonImmutable::instance($lastModifyDate)
            ->setTimezone((string) config('banking.bank_timezone', 'Europe/Moscow'));
        $query = [
            'accountNumber' => $account->account_number,
            'lastModifyDate' => $cursor->format('Y-m-d\TH:i:s.v'),
            'page' => 1,
        ];

        if ($lastModifyDateTo) {
            $to = CarbonImmutable::instance($lastModifyDateTo)
                ->setTimezone((string) config('banking.bank_timezone', 'Europe/Moscow'));

            if ($to->lessThanOrEqualTo($cursor)) {
                throw new BankValidationException('Incremental statement end must be after its start.');
            }

            $query['lastModifyDateTo'] = $to->format('Y-m-d\TH:i:s.v');
        }

        return $this->loadPages(
            connection: $connection,
            account: $account,
            endpointAlias: 'statement.increment',
            initialQuery: $query,
            cursor: CarbonImmutable::now(),
        );
    }

    public function transaction(
        BankConnection $connection,
        BankAccount $account,
        string $operationId,
        DateTimeInterface $statementDate,
    ): BankTransactionData {
        if (preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $operationId) !== 1) {
            throw new BankValidationException('Sber operation ID is invalid.');
        }

        $payload = $this->client->request(
            $connection->environment,
            'statement.transaction',
            query: [
                'accountNumber' => $account->account_number,
                'statementDate' => CarbonImmutable::instance($statementDate)->format('Y-m-d'),
                'operationId' => $operationId,
            ],
            connection: $connection,
        );

        if (! is_array($payload)) {
            throw new BankMalformedResponseException('Sber operation response is malformed.', 'statement.transaction');
        }

        $operation = data_get($payload, 'transaction')
            ?? data_get($payload, 'operation')
            ?? data_get($payload, 'data')
            ?? $payload;

        if (! is_array($operation)) {
            throw new BankMalformedResponseException('Sber operation response is malformed.', 'statement.transaction');
        }

        return $this->normalizer->transaction($operation);
    }

    private function loadPages(
        BankConnection $connection,
        BankAccount $account,
        string $endpointAlias,
        array $initialQuery,
        ?CarbonImmutable $cursor = null,
    ): BankStatementData {
        $query = $initialQuery;
        $transactions = collect();
        $balances = collect();
        $reloadTime = null;
        $pageCount = 0;
        $seenPages = [$this->pageKey($initialQuery) => true];

        while (true) {
            $pageCount++;

            if ($pageCount > self::MAX_PAGES) {
                throw new BankMalformedResponseException('Sber pagination exceeded the safety limit.', $endpointAlias);
            }

            try {
                $payload = $this->client->request(
                    $connection->environment,
                    $endpointAlias,
                    query: $query,
                    connection: $connection,
                );
            } catch (BankValidationException $exception) {
                if (
                    $exception->httpStatus === 404
                    && in_array($exception->bankCause, ['DATA_NOT_FOUND', 'DATA_NOT_FOUND_EXCEPTION'], true)
                ) {
                    break;
                }

                throw $exception;
            }

            if (! is_array($payload)) {
                throw new BankMalformedResponseException('Sber statement response is malformed.', $endpointAlias);
            }

            $page = $this->normalizer->statementPage($payload, $endpointAlias);
            $transactions->push(...$page->transactions);
            $balances->push(...$page->balances);
            $reloadTime ??= $page->reloadTime;

            if ($page->nextUrl === null) {
                break;
            }

            $query = $this->client->validatePaginationUrl(
                $connection->environment,
                $endpointAlias,
                $page->nextUrl,
            );

            if (($query['accountNumber'] ?? null) !== $account->account_number) {
                throw new BankMalformedResponseException('Sber pagination changed the account number.', $endpointAlias);
            }

            $pageKey = $this->pageKey($query);

            if (isset($seenPages[$pageKey])) {
                throw new BankMalformedResponseException('Sber pagination contains a cycle.', $endpointAlias);
            }

            $seenPages[$pageKey] = true;
        }

        return new BankStatementData(
            transactions: $transactions,
            balances: $balances,
            cursor: $cursor,
            reloadTime: $reloadTime,
            pages: $pageCount,
        );
    }

    private function pageKey(array $query): string
    {
        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
