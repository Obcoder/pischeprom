<?php

namespace App\Console\Commands;

use App\Models\MailingTemplate;
use App\Services\CommercialOffers\UnisenderGoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class MailingsUnisenderSyncTemplateCommand extends Command
{
    protected $signature = 'mailings:unisender:sync-template {templateId}';

    protected $description = 'Sync a local mailing template to Unisender Go.';

    public function handle(UnisenderGoClient $client): int
    {
        $template = MailingTemplate::query()->findOrFail((int) $this->argument('templateId'));
        $response = $client->setTemplate([
            'template' => [
                'id' => $template->unisender_template_id,
                'name' => $template->slug,
                'subject' => $template->subject,
                'body' => ['html' => $template->html_markup, 'plaintext' => $template->plaintext],
            ],
        ]);
        $remoteId = Arr::get($response, 'template_id') ?: Arr::get($response, 'result.template_id') ?: Arr::get($response, 'id');
        if ($remoteId) {
            $template->update(['unisender_template_id' => (string) $remoteId]);
        }

        $this->info('Template synced.');
        $this->line('Local ID: '.$template->id.' Remote ID: '.($remoteId ?: $template->unisender_template_id ?: '-'));

        return self::SUCCESS;
    }
}
