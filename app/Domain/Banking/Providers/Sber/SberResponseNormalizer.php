<?php

namespace App\Domain\Banking\Providers\Sber;

use App\Domain\Banking\DTO\BankAccountData;
use App\Domain\Banking\DTO\BankBalanceData;
use App\Domain\Banking\DTO\BankTransactionData;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Exceptions\BankMalformedResponseException;
use App\Domain\Banking\Services\BankAccountMasker;
use App\Domain\Banking\Services\DecimalMoney;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Throwable;

class SberResponseNormalizer
{
    /** @return Collection<int, BankAccountData> */
    public function accounts(array $payload): Collection
    {
        $items = $this->first($payload, [
            'accounts',
            'data.accounts',
            'accountList',
            'data.accountList',
        ], []);

        if (! is_array($items)) {
            throw new BankMalformedResponseException('Sber account list has an invalid structure.', 'oauth.user_info');
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new BankMalformedResponseException('Sber account list contains an invalid item.', 'oauth.user_info');
            }
        }

        return collect($items)
            ->map(function (array $item): BankAccountData {
                $accountNumber = $this->boundedDigits(
                    $this->first($item, [
                        'accountNumber',
                        'number',
                        'account.number',
                    ]),
                    34,
                    'account number',
                    'oauth.user_info',
                );

                if ($accountNumber === null || strlen($accountNumber) < 5) {
                    throw new BankMalformedResponseException('Sber account item lacks an account number.', 'oauth.user_info');
                }

                return new BankAccountData(
                    externalId: $this->boundedString(
                        $this->first($item, ['id', 'accountId', 'externalId']),
                        255,
                        'account ID',
                        'oauth.user_info',
                    ),
                    accountNumber: $accountNumber,
                    maskedNumber: BankAccountMasker::mask($accountNumber) ?? '',
                    name: $this->boundedString(
                        $this->first($item, ['name', 'accountName', 'productName']),
                        255,
                        'account name',
                        'oauth.user_info',
                    ),
                    type: $this->boundedString(
                        $this->first($item, ['type', 'accountType', 'productType']),
                        64,
                        'account type',
                        'oauth.user_info',
                    ),
                    currency: $this->currency($this->first($item, ['currency', 'currencyCode', 'account.currency']), 'RUB'),
                    status: $this->accountStatus($this->first($item, ['status', 'state', 'active'])),
                    balance: $this->accountBalance($item),
                    requisites: array_filter([
                        'bank_name' => $this->boundedString(
                            $this->first($item, ['bankName', 'bank.name']),
                            1024,
                            'bank name',
                            'oauth.user_info',
                        ),
                        'bic' => $this->boundedDigits(
                            $this->first($item, ['bic', 'bank.bic']),
                            16,
                            'bank BIC',
                            'oauth.user_info',
                        ),
                        'corr_account' => $this->boundedDigits(
                            $this->first($item, [
                                'corrAccountNumber',
                                'correspondentAccount',
                                'bank.corrAccount',
                            ]),
                            34,
                            'correspondent account',
                            'oauth.user_info',
                        ),
                    ], fn (mixed $value): bool => $value !== null),
                    rawPayload: $item,
                );
            })
            ->values();
    }

    public function statementPage(array $payload, string $source): SberStatementPage
    {
        $items = $this->first($payload, [
            'transactions',
            'operations',
            'statement.transactions',
            'statement.operations',
            'data.transactions',
            'data.operations',
            'data.items',
        ]);

        if ($items === null && isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            $items = $payload['data'];
        }

        if ($items === null) {
            $items = [];
        }

        if (! is_array($items)) {
            throw new BankMalformedResponseException('Sber statement operation list has an invalid structure.', $source);
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new BankMalformedResponseException('Sber statement contains an invalid operation item.', $source);
            }
        }

        $transactions = collect($items)
            ->map(fn (array $item): BankTransactionData => $this->transaction($item))
            ->values();
        $balances = collect();
        $balance = $this->statementBalance($payload, $source);

        if ($balance) {
            $balances->push($balance);
        }

        return new SberStatementPage(
            transactions: $transactions,
            balances: $balances,
            nextUrl: $this->nextUrl($payload, $source),
            reloadTime: $this->dateTime($this->first($payload, [
                'reloadTime',
                'data.reloadTime',
                'statement.reloadTime',
            ])),
        );
    }

    public function transaction(array $item): BankTransactionData
    {
        $rawAmount = $this->first($item, [
            'amount.amount',
            'amount.value',
            'operationAmount.amount',
            'operationAmount.value',
            'operationAmount',
            'amount',
            'sum',
        ]);
        $amount = $this->money($rawAmount);
        $direction = $this->direction($item, $amount);

        if (DecimalMoney::compare($amount, '0.00') < 0) {
            $amount = DecimalMoney::subtract('0.00', $amount);
        }

        $operationDate = $this->dateTime($this->first($item, [
            'operationDate',
            'date',
            'documentDate',
            'createDate',
        ]));

        if (! $operationDate) {
            throw new BankMalformedResponseException('Sber operation lacks a valid operation date.', 'statement');
        }

        return new BankTransactionData(
            operationId: $this->boundedString(
                $this->first($item, ['operationId', 'transactionId', 'id']),
                255,
                'operation ID',
            ),
            operationDate: $operationDate,
            postingDate: $this->dateTime($this->first($item, [
                'postingDate',
                'executionDate',
                'accountingDate',
                'processDate',
            ])),
            valueDate: $this->dateTime($this->first($item, ['valueDate', 'paymentDate'])),
            direction: $direction,
            amount: $amount,
            currency: $this->currency($this->first($item, [
                'amount.currencyName',
                'amount.currency',
                'operationAmount.currencyName',
                'operationAmount.currency',
                'currencyName',
                'currency',
                'currencyCode',
            ]), 'RUB'),
            status: $this->transactionStatus($this->first($item, [
                'status',
                'operationStatus',
                'state',
            ])),
            documentNumber: $this->boundedString(
                $this->first($item, ['documentNumber', 'docNumber', 'number']),
                128,
                'document number',
            ),
            purpose: $this->boundedString(
                $this->first($item, [
                    'purpose',
                    'paymentPurpose',
                    'rurTransfer.paymentPurpose',
                    'rurTransfer.purpose',
                    'paymentDetails',
                    'description',
                ]),
                4096,
                'payment purpose',
            ),
            payerName: $this->partyString($item, 'payer', ['name', 'fullName'], [
                'payerName',
                'rurTransfer.payerName',
            ]),
            payerInn: $this->partyDigits($item, 'payer', ['inn', 'taxId'], [
                'payerInn',
                'rurTransfer.payerInn',
            ], 16, 'payer INN'),
            payerKpp: $this->partyDigits($item, 'payer', ['kpp'], [
                'payerKpp',
                'rurTransfer.payerKpp',
            ], 16, 'payer KPP'),
            payerAccount: $this->partyDigits($item, 'payer', ['accountNumber', 'account.number', 'account'], [
                'payerAccount',
                'payerAccountNumber',
                'rurTransfer.payerAccount',
            ], 34, 'payer account'),
            payerBankName: $this->partyString($item, 'payer', ['bank.name', 'bankName'], [
                'payerBankName',
                'rurTransfer.payerBankName',
            ]),
            payerBic: $this->partyDigits($item, 'payer', ['bank.bic', 'bic'], [
                'payerBic',
                'payerBankBic',
                'rurTransfer.payerBankBic',
            ], 16, 'payer BIC'),
            payerCorrAccount: $this->partyDigits($item, 'payer', ['bank.corrAccount', 'corrAccount'], [
                'payerCorrAccount',
                'payerBankCorrAccount',
                'rurTransfer.payerBankCorrAccount',
            ], 34, 'payer correspondent account'),
            recipientName: $this->partyString($item, 'recipient', ['name', 'fullName'], [
                'recipientName',
                'payeeName',
                'rurTransfer.payeeName',
            ]),
            recipientInn: $this->partyDigits($item, 'recipient', ['inn', 'taxId'], [
                'recipientInn',
                'payeeInn',
                'rurTransfer.payeeInn',
            ], 16, 'recipient INN'),
            recipientKpp: $this->partyDigits($item, 'recipient', ['kpp'], [
                'recipientKpp',
                'payeeKpp',
                'rurTransfer.payeeKpp',
            ], 16, 'recipient KPP'),
            recipientAccount: $this->partyDigits($item, 'recipient', ['accountNumber', 'account.number', 'account'], [
                'recipientAccount',
                'payeeAccount',
                'rurTransfer.payeeAccount',
            ], 34, 'recipient account'),
            recipientBankName: $this->partyString($item, 'recipient', ['bank.name', 'bankName'], [
                'recipientBankName',
                'payeeBankName',
                'rurTransfer.payeeBankName',
            ]),
            recipientBic: $this->partyDigits($item, 'recipient', ['bank.bic', 'bic'], [
                'recipientBic',
                'payeeBic',
                'payeeBankBic',
                'rurTransfer.payeeBankBic',
            ], 16, 'recipient BIC'),
            recipientCorrAccount: $this->partyDigits($item, 'recipient', ['bank.corrAccount', 'corrAccount'], [
                'recipientCorrAccount',
                'payeeCorrAccount',
                'payeeBankCorrAccount',
                'rurTransfer.payeeBankCorrAccount',
            ], 34, 'recipient correspondent account'),
            bankModifiedAt: $this->dateTime($this->first($item, [
                'lastModifyDate',
                'modifiedAt',
                'updateDate',
                'updatedAt',
            ])),
            rawPayload: $item,
        );
    }

    public function balanceSummary(array $payload, DateTimeInterface $statementDate): BankBalanceData
    {
        $balance = $this->statementBalance(
            $payload,
            'statement.summary',
            CarbonImmutable::instance($statementDate),
        );

        if (! $balance) {
            throw new BankMalformedResponseException(
                'Sber statement summary lacks a closing balance.',
                'statement.summary'
            );
        }

        return $balance;
    }

    private function accountBalance(array $item): ?BankBalanceData
    {
        $amount = $this->first($item, [
            'balance.amount',
            'balance.value',
            'currentBalance.amount',
            'currentBalance',
            'balance',
        ]);

        if ($amount === null || is_array($amount)) {
            return null;
        }

        return new BankBalanceData(
            type: 'last_known',
            amount: $this->money($amount, 'oauth.user_info'),
            currency: $this->currency($this->first($item, [
                'balance.currencyName',
                'balance.currency',
                'currentBalance.currencyName',
                'currentBalance.currency',
                'currencyName',
                'currency',
                'currencyCode',
            ]), 'RUB'),
            statementDate: null,
            asOf: $this->dateTime($this->first($item, [
                'balance.asOf',
                'balanceDate',
                'updatedAt',
            ])) ?? CarbonImmutable::now(),
            source: 'user_info',
        );
    }

    private function statementBalance(
        array $payload,
        string $source,
        ?CarbonImmutable $fallbackStatementDate = null,
    ): ?BankBalanceData {
        $amount = $this->first($payload, [
            'closingBalance.amount',
            'closingBalance.value',
            'closingBalance',
            'outgoingBalance.amount',
            'outgoingBalance',
            'statement.closingBalance.amount',
            'statement.closingBalance',
            'data.closingBalance.amount',
            'data.closingBalance',
            'saldoOut',
            'balance.amount',
        ]);

        if ($amount === null || is_array($amount)) {
            return null;
        }

        $statementDate = $this->dateTime($this->first($payload, [
            'statementDate',
            'statement.date',
            'data.statementDate',
        ])) ?? $fallbackStatementDate;

        return new BankBalanceData(
            type: 'closing',
            amount: $this->money($amount, $source),
            currency: $this->currency($this->first($payload, [
                'closingBalance.currencyName',
                'closingBalance.currency',
                'outgoingBalance.currencyName',
                'outgoingBalance.currency',
                'statement.currencyName',
                'statement.currency',
                'currencyName',
                'currency',
                'currencyCode',
            ]), 'RUB'),
            statementDate: $statementDate,
            asOf: $this->dateTime($this->first($payload, [
                'asOf',
                'composedDateTime',
                'lastModifyDate',
                'updatedAt',
                'data.asOf',
            ])) ?? CarbonImmutable::now(),
            source: $source,
        );
    }

    private function direction(array $item, string $amount): BankTransactionDirection
    {
        $value = mb_strtoupper((string) $this->first($item, [
            'direction',
            'creditDebitIndicator',
            'debitCredit',
            'operationType',
            'type',
        ], ''));

        if (
            in_array($value, ['C', 'CR', 'CREDIT', 'IN', 'INCOME', 'RECEIPT', 'ПРИХОД', 'ПОСТУПЛЕНИЕ'], true)
            || str_contains($value, 'CREDIT')
            || str_contains($value, 'ПРИХОД')
        ) {
            return BankTransactionDirection::Credit;
        }

        if (
            in_array($value, ['D', 'DR', 'DEBIT', 'OUT', 'OUTCOME', 'EXPENSE', 'РАСХОД', 'СПИСАНИЕ'], true)
            || str_contains($value, 'DEBIT')
            || str_contains($value, 'РАСХОД')
            || str_contains($value, 'СПИС')
        ) {
            return BankTransactionDirection::Debit;
        }

        return DecimalMoney::compare($amount, '0.00') < 0
            ? BankTransactionDirection::Debit
            : BankTransactionDirection::Credit;
    }

    private function transactionStatus(mixed $value): BankTransactionStatus
    {
        $status = mb_strtoupper(trim((string) $value));

        if ($status === '') {
            return BankTransactionStatus::Posted;
        }

        if (in_array($status, ['POSTED', 'EXECUTED', 'PROCESSED', 'SUCCESS', 'COMPLETED', 'ПРОВЕДЕН', 'ПРОВЕДЕНО'], true)) {
            return BankTransactionStatus::Posted;
        }

        if (str_contains($status, 'CANCEL') || str_contains($status, 'ОТМЕН')) {
            return BankTransactionStatus::Cancelled;
        }

        if (str_contains($status, 'REVERSE') || str_contains($status, 'СТОРН')) {
            return BankTransactionStatus::Reversed;
        }

        if (str_contains($status, 'PEND') || str_contains($status, 'PROCESS') || str_contains($status, 'ОЖИД')) {
            return BankTransactionStatus::Pending;
        }

        return BankTransactionStatus::Unknown;
    }

    private function accountStatus(mixed $value): string
    {
        if (is_bool($value) || is_int($value)) {
            return $value ? 'active' : 'closed';
        }

        $status = mb_strtoupper(trim((string) $value));

        return in_array($status, ['', 'ACTIVE', 'OPEN', '1', 'ДЕЙСТВУЮЩИЙ'], true)
            ? 'active'
            : (in_array($status, ['CLOSED', 'CLOSE', '0', 'ЗАКРЫТ'], true) ? 'closed' : 'unknown');
    }

    private function nextUrl(array $payload, string $source): ?string
    {
        $links = $this->first($payload, [
            'links',
            '_links',
            'data.links',
            'statement.links',
        ], []);

        if (! is_array($links)) {
            throw new BankMalformedResponseException('Sber statement links have an invalid structure.', $source);
        }

        if (array_key_exists('next', $links)) {
            if (is_string($links['next'])) {
                return $links['next'];
            }

            if (is_array($links['next']) && is_string($links['next']['href'] ?? null)) {
                return $links['next']['href'];
            }

            throw new BankMalformedResponseException('Sber next-page link is malformed.', $source);
        }

        foreach ($links as $link) {
            if (
                is_array($link)
                && mb_strtolower((string) ($link['rel'] ?? '')) === 'next'
            ) {
                if (! is_string($link['href'] ?? null)) {
                    throw new BankMalformedResponseException('Sber next-page link is malformed.', $source);
                }

                return $link['href'];
            }
        }

        return null;
    }

    private function partyString(array $item, string $party, array $nested, array $flat): ?string
    {
        return $this->boundedString(
            $this->first($item, [
                ...array_map(fn (string $path): string => "{$party}.{$path}", $nested),
                ...$flat,
            ]),
            1024,
            "{$party} name",
        );
    }

    private function partyDigits(
        array $item,
        string $party,
        array $nested,
        array $flat,
        int $maxLength,
        string $field,
    ): ?string {
        return $this->boundedDigits(
            $this->first($item, [
                ...array_map(fn (string $path): string => "{$party}.{$path}", $nested),
                ...$flat,
            ]),
            $maxLength,
            $field,
        );
    }

    private function first(array $payload, array $paths, mixed $default = null): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 4096);
    }

    private function digits(mixed $value): ?string
    {
        $value = $this->string($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $value);

        return $digits === '' ? null : $digits;
    }

    private function boundedString(
        mixed $value,
        int $maxLength,
        string $field,
        string $endpoint = 'statement',
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new BankMalformedResponseException(
                "Sber {$field} exceeds the supported length.",
                $endpoint,
            );
        }

        return $value;
    }

    private function boundedDigits(
        mixed $value,
        int $maxLength,
        string $field,
        string $endpoint = 'statement',
    ): ?string {
        $digits = $this->digits($value);

        if ($digits !== null && strlen($digits) > $maxLength) {
            throw new BankMalformedResponseException(
                "Sber {$field} exceeds the supported length.",
                $endpoint,
            );
        }

        return $digits;
    }

    private function currency(mixed $value, string $default): string
    {
        $currency = mb_strtoupper(trim((string) $value));

        if ($currency === 'RUR') {
            return 'RUB';
        }

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : $default;
    }

    private function decimalString(mixed $value, string $endpoint = 'statement'): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new BankMalformedResponseException(
                'Sber amount is not a safe decimal value.',
                $endpoint,
            );
        }

        return (string) $value;
    }

    private function money(mixed $value, string $endpoint = 'statement'): string
    {
        try {
            return DecimalMoney::normalize($this->decimalString($value, $endpoint));
        } catch (BankMalformedResponseException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new BankMalformedResponseException(
                'Sber amount is outside the supported decimal range.',
                $endpoint,
            );
        }
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, (string) config('banking.bank_timezone', 'Europe/Moscow'));
        } catch (Throwable) {
            return null;
        }
    }
}
