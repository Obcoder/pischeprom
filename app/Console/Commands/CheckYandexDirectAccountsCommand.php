<?php

namespace App\Console\Commands;

use App\Models\YandexAccount;
use App\Services\Yandex\YandexOAuthService;
use Illuminate\Console\Command;
use Throwable;

class CheckYandexDirectAccountsCommand extends Command
{
    protected $signature = 'yandex:direct:check-accounts';

    protected $description = 'Check Yandex OAuth accounts for Direct/Metrica';

    public function handle(YandexOAuthService $service): int
    {
        $accounts = YandexAccount::query()->where('is_active', true)->get();

        foreach ($accounts as $account) {
            try {
                $service->check($account);
                $this->info("{$account->id}: ok");
            } catch (Throwable $e) {
                $account->update(['is_active' => false]);
                $this->error("{$account->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
