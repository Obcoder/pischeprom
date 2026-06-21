<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\UnisenderGoClient;
use Illuminate\Console\Command;

class MailingsUnisenderTestApiCommand extends Command
{
    protected $signature = 'mailings:unisender:test-api';

    protected $description = 'Check Unisender Go API connectivity without printing secrets.';

    public function handle(UnisenderGoClient $client): int
    {
        $response = $client->listSuppression(['limit' => 1]);
        $this->info('Unisender Go API responded.');
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
