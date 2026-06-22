<?php

namespace App\Console\Commands;

use App\Services\Mail\MailboxRegistry;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

class ListYandexMailFoldersCommand extends Command
{
    protected $signature = 'mail:yandex-folders {--mailbox=}';

    protected $description = 'List Yandex IMAP folders';

    public function handle(MailboxRegistry $mailboxes): int
    {
        $mailbox = $mailboxes->findOrDefault($this->option('mailbox') ?: null);
        $manager = new ClientManager();
        $client = $manager->make($mailboxes->imapClientConfig($mailbox));

        $client->connect();

        $this->info('Folders for ' . $mailbox['address'] . ':');

        foreach ($client->getFolders() as $folder) {
            $path = $folder->path ?? null;
            $name = $folder->name ?? null;

            $this->line(sprintf(
                            'path: %s | name: %s',
                            $path ?: '—',
                            $name ?: '—',
                        ));
        }

        $client->disconnect();

        return self::SUCCESS;
    }
}
