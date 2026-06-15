<?php

namespace App\Jobs;

use App\Models\YandexAccount;
use App\Services\Yandex\AI\DirectAiAutopilotEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DirectAiAutopilotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public ?int $accountId = null) {}

    public function handle(DirectAiAutopilotEngine $engine): void
    {
        $account = $this->accountId ? YandexAccount::query()->find($this->accountId) : null;

        if ($this->accountId && !$account) {
            return;
        }

        $engine->processCycle($account);
    }
}
