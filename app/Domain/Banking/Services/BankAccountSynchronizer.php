<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\DTO\BankAccountData;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankAccountSynchronizer
{
    public function __construct(private readonly BankProviderInterface $provider) {}

    /**
     * @return array{received:int,created:int,updated:int,account_ids:array<int,int>}
     */
    public function synchronize(BankConnection $connection): array
    {
        $accounts = $this->provider->getAccounts($connection);
        $created = 0;
        $updated = 0;
        $ids = [];

        foreach ($accounts as $data) {
            if (! $data instanceof BankAccountData) {
                continue;
            }

            $wasCreated = false;
            $account = DB::transaction(function () use ($connection, $data, &$wasCreated): BankAccount {
                $account = BankAccount::query()
                    ->where('bank_connection_id', $connection->id)
                    ->where('account_number', $data->accountNumber)
                    ->lockForUpdate()
                    ->first() ?? new BankAccount;
                $wasCreated = ! $account->exists;
                $account->forceFill([
                    'bank_connection_id' => $connection->id,
                    'external_id' => $data->externalId,
                    'account_number' => $data->accountNumber,
                    'masked_number' => $data->maskedNumber,
                    'name' => $data->name,
                    'type' => $data->type,
                    'currency' => $data->currency,
                    'status' => $data->status,
                    'current_balance' => $data->balance?->amount,
                    'balance_as_of' => $data->balance?->asOf,
                    'balance_statement_date' => $data->balance?->statementDate,
                    'last_synced_at' => now(),
                    'normalized_requisites' => $data->requisites,
                    'raw_payload' => $data->rawPayload,
                ])->save();

                if ($data->balance) {
                    $this->storeBalance($account, $data->balance);
                }

                return $account;
            }, 3);

            $wasCreated ? $created++ : $updated++;
            $ids[] = $account->id;
        }

        return [
            'received' => $accounts->count(),
            'created' => $created,
            'updated' => $updated,
            'account_ids' => $ids,
        ];
    }

    public function storeBalances(BankAccount $account, Collection $balances): void
    {
        foreach ($balances as $balance) {
            if ($balance instanceof \App\Domain\Banking\DTO\BankBalanceData) {
                $this->storeBalance($account, $balance);
            }
        }
    }

    private function storeBalance(
        BankAccount $account,
        \App\Domain\Banking\DTO\BankBalanceData $balance,
    ): void {
        DB::transaction(function () use ($account, $balance): void {
            $locked = BankAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $locked->balanceSnapshots()->firstOrCreate([
                'type' => $balance->type,
                'amount' => $balance->amount,
                'currency' => $balance->currency,
                'statement_date' => $balance->statementDate?->format('Y-m-d'),
                'as_of' => $balance->asOf,
                'source' => $balance->source,
            ]);

            if (! $this->isNewerBalance($locked, $balance)) {
                return;
            }

            $locked->forceFill([
                'current_balance' => $balance->amount,
                'currency' => $balance->currency,
                'balance_as_of' => $balance->asOf,
                'balance_statement_date' => $balance->statementDate,
            ])->save();
        }, 3);

        $account->refresh();
    }

    private function isNewerBalance(
        BankAccount $account,
        \App\Domain\Banking\DTO\BankBalanceData $balance,
    ): bool {
        if ($account->balance_as_of === null) {
            return true;
        }

        if ($account->balance_statement_date !== null && $balance->statementDate !== null) {
            $incomingDate = $balance->statementDate->startOfDay();
            $currentDate = $account->balance_statement_date->startOfDay();

            if (! $incomingDate->equalTo($currentDate)) {
                return $incomingDate->greaterThan($currentDate);
            }
        }

        return $balance->asOf->greaterThanOrEqualTo($account->balance_as_of);
    }
}
