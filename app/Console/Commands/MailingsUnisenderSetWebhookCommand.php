<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\UnisenderGoClient;
use Illuminate\Console\Command;

class MailingsUnisenderSetWebhookCommand extends Command
{
    protected $signature = 'mailings:unisender:set-webhook {--url= : Override webhook URL}';

    protected $description = 'Configure Unisender Go webhook for commercial offer mailings.';

    public function handle(UnisenderGoClient $client): int
    {
        $config = $client->defaultWebhookConfig();
        if ($this->option('url')) {
            $config['url'] = $this->option('url');
        }

        $response = $client->setWebhook($config);
        $this->info('Webhook configured.');
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
